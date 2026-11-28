<?php

namespace App\Core\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Class UserSynchronizationService
 *
 * Synchronizes Keycloak OIDC authenticated claims with local Laravel user profiles.
 * Maintains zero password storage while keeping user profiles, MFA status,
 * lifecycle status, and jurisdictional scopes current.
 *
 * @package App\Core\Services
 */
class UserSynchronizationService
{
    /**
     * Synchronize local user record from validated Keycloak claims.
     */
    public function syncFromClaims(array $claims): User
    {
        $keycloakSub = $claims['sub'] ?? null;
        $username = $claims['preferred_username'] ?? null;
        $email = $claims['email'] ?? (($username ?: 'officer') . '@modmr.gov.bd');
        $fullName = $claims['name'] ?? $username ?? 'Government Officer';

        // Extract roles (preferring explicit IDMS ROLE_* over Keycloak default realm roles)
        $roles = $claims['roles'] ?? $claims['realm_access']['roles'] ?? [];
        $idmsRoles = array_values(array_filter(
            $roles,
            fn ($r) => is_string($r) && str_starts_with($r, 'ROLE_')
        ));
        $primaryRole = $idmsRoles[0] ?? ($roles[0] ?? 'ROLE_FIELD_ENUMERATOR');

        // Extract administrative attributes
        $tierLevel = $claims['tier_level'] ?? $claims['administrative_profile']['tier_level'] ?? 'NATIONAL';
        $ancestryPath = $claims['ancestry_path'] ?? $claims['administrative_profile']['ancestry_path'] ?? '/';
        $scopeId = $claims['scope_id'] ?? $claims['administrative_profile']['scope_id'] ?? null;
        $designation = $claims['designation'] ?? $claims['administrative_profile']['designation'] ?? null;

        // Detect MFA verification from OIDC AMR / ACR claims
        $amr = (array) ($claims['amr'] ?? []);
        $acr = (string) ($claims['acr'] ?? '');
        $mfaVerifiedFromClaims = in_array('otp', $amr, true)
            || in_array('mfa', $amr, true)
            || in_array('totp', $amr, true)
            || str_contains(strtolower($acr), 'aal2')
            || str_contains(strtolower($acr), 'mfa');

        $mfaRequiredTier = in_array(strtoupper((string) $tierLevel), ['NATIONAL', 'DIRECTORATE', 'DIVISION', 'DISTRICT', 'UPAZILA'], true);

        // Locate existing user by Keycloak Subject UUID or email
        $user = User::where(function ($q) use ($keycloakSub, $email) {
            if ($keycloakSub) {
                $q->where('keycloak_sub', $keycloakSub)->orWhere('email', $email);
            } else {
                $q->where('email', $email);
            }
        })->first();

        $isNew = false;
        if (!$user) {
            $isNew = true;
            $user = new User();
            $user->id = (string) Str::uuid();
            $user->keycloak_sub = $keycloakSub;
            $user->is_active = true;
            $user->account_status = User::STATUS_ACTIVE;
        }

        // Preserve locally enforced suspension, deactivation, lock, or pending state
        $isRestricted = !$isNew && (
            !$user->is_active
            || in_array($user->account_status, [
                User::STATUS_SUSPENDED,
                User::STATUS_DEACTIVATED,
                User::STATUS_LOCKED,
                User::STATUS_PENDING,
            ], true)
        );

        if ($isRestricted) {
            $user->setJwtClaims(array_merge($claims, [
                'administrative_profile' => [
                    'tier_level' => $tierLevel,
                    'ancestry_path' => $ancestryPath,
                    'scope_id' => $scopeId,
                    'designation' => $user->designation ?? $designation,
                ],
            ]));

            return $user;
        }

        if ($keycloakSub && !$user->keycloak_sub) {
            $user->keycloak_sub = $keycloakSub;
        }
        $user->keycloak_username = $username ?? $user->keycloak_username;
        $user->email = $email;
        $user->full_name_en = $fullName;
        $user->role_code = $primaryRole;
        $user->designation = $designation ?? $user->designation;
        $user->is_active = true;
        $user->account_status = User::STATUS_ACTIVE;
        $user->idp_sync_status = User::IDP_SYNC_LINKED;
        $user->idp_synced_at = now();
        $user->idp_sync_error = null;
        $user->mfa_enforced = (bool) ($user->mfa_enforced || $mfaRequiredTier);
        if ($mfaVerifiedFromClaims) {
            $user->mfa_verified = true;
            $user->mfa_enrolled_at = $user->mfa_enrolled_at ?? now();
        }
        $user->last_login_at = now();

        $resolvedScopeId = null;
        if ($scopeId && AdministrativeUnit::whereKey($scopeId)->exists()) {
            $resolvedScopeId = $scopeId;
        } elseif ($ancestryPath) {
            $resolvedScopeId = AdministrativeUnit::where('ancestry_path', $ancestryPath)->value('id');
        }
        if ($resolvedScopeId) {
            $user->administrative_unit_id = $resolvedScopeId;
        }

        $user->save();

        $this->syncRolePivotAndPrimaryScope($user, $idmsRoles ?: [$primaryRole], $resolvedScopeId);

        // Hydrate runtime claims for scope and role evaluation
        $user->setJwtClaims(array_merge($claims, [
            'administrative_profile' => [
                'tier_level' => $tierLevel,
                'ancestry_path' => $ancestryPath,
                'scope_id' => $resolvedScopeId ?? $scopeId,
                'designation' => $user->designation ?? $designation,
            ],
        ]));

        return $user;
    }

    /**
     * Keep core.user_roles and core.user_administrative_scopes aligned when User Management tables exist.
     */
    protected function syncRolePivotAndPrimaryScope(User $user, array $roleCodes, ?string $resolvedScopeId): void
    {
        try {
            $conn = $user->getConnectionName();
            $rolesTable = $user->getConnection()->getDriverName() === 'pgsql' ? 'core.roles' : 'roles';
            if (!Schema::connection($conn)->hasTable($rolesTable)) {
                return;
            }

            $roleIds = Role::whereIn('code', $roleCodes)->pluck('id', 'code');
            if ($roleIds->isNotEmpty()) {
                foreach ($roleIds as $code => $roleId) {
                    UserRole::updateOrCreate(
                        ['user_id' => $user->id, 'role_id' => $roleId],
                        ['is_primary' => $code === $user->role_code, 'assigned_at' => now()]
                    );
                }
            }

            $scopesTable = $user->getConnection()->getDriverName() === 'pgsql'
                ? 'core.user_administrative_scopes'
                : 'user_administrative_scopes';
            if ($resolvedScopeId && Schema::connection($conn)->hasTable($scopesTable)) {
                UserAdministrativeScope::where('user_id', $user->id)
                    ->where('assignment_type', 'PRIMARY_POSTING')
                    ->where('administrative_unit_id', '!=', $resolvedScopeId)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'valid_until' => now()]);

                $hasPrimary = UserAdministrativeScope::where('user_id', $user->id)
                    ->where('assignment_type', 'PRIMARY_POSTING')
                    ->where('administrative_unit_id', $resolvedScopeId)
                    ->where('is_active', true)
                    ->exists();

                if (!$hasPrimary) {
                    UserAdministrativeScope::create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'administrative_unit_id' => $resolvedScopeId,
                        'assignment_type' => 'PRIMARY_POSTING',
                        'is_active' => true,
                        'valid_from' => now(),
                    ]);
                }
            }
        } catch (Throwable) {
            // Do not block authentication if optional RBAC catalog tables are not yet populated
        }
    }
}
