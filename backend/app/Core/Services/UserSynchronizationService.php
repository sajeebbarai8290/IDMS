<?php

namespace App\Core\Services;

use App\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class UserSynchronizationService
 *
 * Synchronizes Keycloak OIDC authenticated claims with local Laravel user profiles.
 * Maintains zero password storage while keeping user profiles and jurisdictional scopes current.
 *
 * @package App\Core\Services
 */
class UserSynchronizationService
{
    /**
     * Synchronize local user record from validated Keycloak claims.
     *
     * @param array $claims
     * @return User
     */
    public function syncFromClaims(array $claims): User
    {
        $keycloakSub = $claims['sub'] ?? null;
        $email = $claims['email'] ?? ($claims['preferred_username'] . '@modmr.gov.bd');
        $fullName = $claims['name'] ?? $claims['preferred_username'] ?? 'Government Officer';

        // Extract roles
        $roles = $claims['roles'] ?? $claims['realm_access']['roles'] ?? [];
        $primaryRole = $roles[0] ?? 'ROLE_FIELD_ENUMERATOR';

        // Extract administrative attributes
        $tierLevel = $claims['tier_level'] ?? $claims['administrative_profile']['tier_level'] ?? 'NATIONAL';
        $ancestryPath = $claims['ancestry_path'] ?? $claims['administrative_profile']['ancestry_path'] ?? '/';
        $scopeId = $claims['scope_id'] ?? $claims['administrative_profile']['scope_id'] ?? null;
        $designation = $claims['designation'] ?? $claims['administrative_profile']['designation'] ?? null;

        $tableName = DB::getDriverName() === 'pgsql' ? 'core.users' : 'users';

        // Locate existing user by Keycloak Subject UUID or email
        $user = User::where('keycloak_sub', $keycloakSub)
            ->orWhere('email', $email)
            ->first();

        if (!$user) {
            $user = new User();
            $user->id = (string) Str::uuid();
            $user->keycloak_sub = $keycloakSub;
        }

        // Update attributes (passwords are never touched)
        $user->email = $email;
        $user->full_name_en = $fullName;
        $user->role_code = $primaryRole;
        $user->designation = $designation;
        $user->is_active = true;
        $user->last_login_at = now();

        if ($scopeId) {
            $user->administrative_unit_id = $scopeId;
        }

        $user->save();

        // Hydrate runtime claims for scope and role evaluation
        $user->setJwtClaims(array_merge($claims, [
            'administrative_profile' => [
                'tier_level' => $tierLevel,
                'ancestry_path' => $ancestryPath,
                'scope_id' => $scopeId,
                'designation' => $designation,
            ],
        ]));

        return $user;
    }
}
