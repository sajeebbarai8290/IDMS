<?php

namespace App\Modules\UserManagement\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\UserManagement\Contracts\KeycloakAdminServiceInterface;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Policies\UserManagementPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserManagementService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected UserManagementPolicy $policy,
        protected KeycloakAdminServiceInterface $keycloakAdmin
    ) {}

    public function bumpDashboardCacheVersion(): void
    {
        $current = (int) Cache::get('user_mgmt:dashboard:version', 1);
        Cache::forever('user_mgmt:dashboard:version', $current + 1);
    }

    public function userRelations(): array
    {
        return [
            'organization',
            'administrativeUnit',
            'roles.permissions',
            'userRoles.role',
            'administrativeScopes.administrativeUnit',
            'administrativeScopes.delegatedByUser',
            'statusChangedByUser',
        ];
    }

    public function applyUserFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['account_status'])) {
            $query->where('account_status', strtoupper((string) $filters['account_status']));
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['role_code'])) {
            $roleCode = (string) $filters['role_code'];
            $query->where(function (Builder $q) use ($roleCode) {
                $q->where('role_code', $roleCode)
                    ->orWhereHas('roles', fn (Builder $rq) => $rq->where('code', $roleCode));
            });
        }

        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (!empty($filters['administrative_unit_id'])) {
            $unit = AdministrativeUnit::find($filters['administrative_unit_id']);
            if ($unit) {
                $norm = rtrim($unit->ancestry_path, '/') . '/';
                $query->whereHas('administrativeUnit', fn (Builder $aq) => $aq->where('ancestry_path', 'like', $norm . '%'));
            }
        }

        if (!empty($filters['tier_level'])) {
            $tier = strtoupper((string) $filters['tier_level']);
            $query->whereHas('administrativeUnit', fn (Builder $aq) => $aq->where('tier_level', $tier));
        }

        if (!empty($filters['idp_sync_status'])) {
            $query->where('idp_sync_status', strtoupper((string) $filters['idp_sync_status']));
        }

        if (isset($filters['mfa_enforced']) && $filters['mfa_enforced'] !== '') {
            $query->where('mfa_enforced', filter_var($filters['mfa_enforced'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('full_name_en', 'like', $term)
                    ->orWhere('full_name_bn', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('employee_id', 'like', $term)
                    ->orWhere('mobile_number', 'like', $term)
                    ->orWhere('designation', 'like', $term)
                    ->orWhere('keycloak_username', 'like', $term);
            });
        }
    }

    public function createUser(array $data, User $actor): User
    {
        $unit = $this->assertUnitInActorScope($actor, $data['administrative_unit_id'], 'primary administrative unit');
        $roleCodes = $this->normalizeAndValidateRequestedRoles($data, $actor);

        $accountStatus = strtoupper((string) ($data['account_status'] ?? User::STATUS_PENDING));
        if (!in_array($accountStatus, User::ACCOUNT_STATUSES, true)) {
            $accountStatus = User::STATUS_PENDING;
        }
        $isActive = $accountStatus === User::STATUS_ACTIVE;

        $mfaEnforced = array_key_exists('mfa_enforced', $data)
            ? (bool) $data['mfa_enforced']
            : $this->requiresMfaByTierOrRoles($unit->tier_level, $roleCodes);

        $user = DB::transaction(function () use ($data, $actor, $unit, $roleCodes, $accountStatus, $isActive, $mfaEnforced) {
            $user = User::create([
                'id' => (string) Str::uuid(),
                'keycloak_sub' => $data['keycloak_sub'] ?? null,
                'keycloak_username' => $data['keycloak_username'] ?? null,
                'idp_sync_status' => !empty($data['keycloak_sub']) ? User::IDP_SYNC_LINKED : User::IDP_SYNC_PENDING,
                'email' => strtolower(trim((string) $data['email'])),
                'employee_id' => !empty($data['employee_id']) ? strtoupper(trim((string) $data['employee_id'])) : null,
                'mobile_number' => $data['mobile_number'] ?? null,
                'full_name_en' => trim((string) $data['full_name_en']),
                'full_name_bn' => $data['full_name_bn'] ?? null,
                'role_code' => $roleCodes[0],
                'administrative_unit_id' => $unit->id,
                'organization_id' => $data['organization_id'] ?? null,
                'department_en' => $data['department_en'] ?? null,
                'department_bn' => $data['department_bn'] ?? null,
                'designation' => $data['designation'] ?? null,
                'designation_bn' => $data['designation_bn'] ?? null,
                'is_active' => $isActive,
                'account_status' => $accountStatus,
                'status_reason' => $data['status_reason'] ?? null,
                'status_changed_at' => now(),
                'status_changed_by' => $actor->id,
                'mfa_enforced' => $mfaEnforced,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncUserRoleRecords($user, $roleCodes, $actor);

            UserAdministrativeScope::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'administrative_unit_id' => $unit->id,
                'assignment_type' => 'PRIMARY_POSTING',
                'is_active' => true,
                'valid_from' => now(),
                'delegated_by' => $actor->id,
                'official_order_ref' => $data['official_order_ref'] ?? null,
            ]);

            AuditLog::log(
                'USER_ACCOUNT_CREATED',
                User::class,
                $user->id,
                null,
                [
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'role_codes' => $roleCodes,
                    'administrative_unit_id' => $unit->id,
                    'account_status' => $accountStatus,
                    'mfa_enforced' => $mfaEnforced,
                ],
                $actor,
                $unit->id
            );

            return $user;
        });

        // Provision / link in Keycloak safely (never stores passwords locally)
        if ($data['sync_with_keycloak'] ?? true) {
            $this->performKeycloakSync($user, $roleCodes, $unit, $actor, true);
        }

        $this->bumpDashboardCacheVersion();

        return $user->fresh($this->userRelations());
    }

    public function updateUser(User $target, array $data, User $actor): User
    {
        $unit = $target->administrativeUnit;
        if (!empty($data['administrative_unit_id']) && $data['administrative_unit_id'] !== $target->administrative_unit_id) {
            $unit = $this->assertUnitInActorScope($actor, $data['administrative_unit_id'], 'primary administrative unit');
        }

        $roleCodes = null;
        if (!empty($data['role_codes']) || !empty($data['role_code'])) {
            $roleCodes = $this->normalizeAndValidateRequestedRoles($data, $actor, $target);
        }

        DB::transaction(function () use ($target, $data, $actor, $unit, $roleCodes) {
            $oldValues = [
                'email' => $target->email,
                'employee_id' => $target->employee_id,
                'full_name_en' => $target->full_name_en,
                'full_name_bn' => $target->full_name_bn,
                'role_code' => $target->role_code,
                'administrative_unit_id' => $target->administrative_unit_id,
                'organization_id' => $target->organization_id,
                'mfa_enforced' => $target->mfa_enforced,
            ];

            $payload = [
                'email' => array_key_exists('email', $data) ? strtolower(trim((string) $data['email'])) : $target->email,
                'employee_id' => array_key_exists('employee_id', $data)
                    ? (!empty($data['employee_id']) ? strtoupper(trim((string) $data['employee_id'])) : null)
                    : $target->employee_id,
                'mobile_number' => array_key_exists('mobile_number', $data) ? $data['mobile_number'] : $target->mobile_number,
                'full_name_en' => array_key_exists('full_name_en', $data) ? trim((string) $data['full_name_en']) : $target->full_name_en,
                'full_name_bn' => array_key_exists('full_name_bn', $data) ? $data['full_name_bn'] : $target->full_name_bn,
                'organization_id' => array_key_exists('organization_id', $data) ? $data['organization_id'] : $target->organization_id,
                'department_en' => array_key_exists('department_en', $data) ? $data['department_en'] : $target->department_en,
                'department_bn' => array_key_exists('department_bn', $data) ? $data['department_bn'] : $target->department_bn,
                'designation' => array_key_exists('designation', $data) ? $data['designation'] : $target->designation,
                'designation_bn' => array_key_exists('designation_bn', $data) ? $data['designation_bn'] : $target->designation_bn,
                'administrative_unit_id' => $unit?->id ?? $target->administrative_unit_id,
                'keycloak_sub' => array_key_exists('keycloak_sub', $data) ? ($data['keycloak_sub'] ?: $target->keycloak_sub) : $target->keycloak_sub,
                'keycloak_username' => array_key_exists('keycloak_username', $data) ? $data['keycloak_username'] : $target->keycloak_username,
                'mfa_enforced' => array_key_exists('mfa_enforced', $data) ? (bool) $data['mfa_enforced'] : $target->mfa_enforced,
                'updated_by' => $actor->id,
            ];

            if ($roleCodes) {
                $payload['role_code'] = $roleCodes[0];
            }

            $target->update($payload);

            if ($roleCodes) {
                $this->syncUserRoleRecords($target, $roleCodes, $actor);
            }

            if ($unit && $unit->id !== $oldValues['administrative_unit_id']) {
                UserAdministrativeScope::where('user_id', $target->id)
                    ->where('assignment_type', 'PRIMARY_POSTING')
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'valid_until' => now()]);

                UserAdministrativeScope::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $target->id,
                    'administrative_unit_id' => $unit->id,
                    'assignment_type' => 'PRIMARY_POSTING',
                    'is_active' => true,
                    'valid_from' => now(),
                    'delegated_by' => $actor->id,
                    'official_order_ref' => $data['official_order_ref'] ?? null,
                ]);
            }

            AuditLog::log(
                'USER_ACCOUNT_UPDATED',
                User::class,
                $target->id,
                $oldValues,
                [
                    'email' => $target->email,
                    'employee_id' => $target->employee_id,
                    'role_code' => $target->role_code,
                    'administrative_unit_id' => $target->administrative_unit_id,
                    'organization_id' => $target->organization_id,
                    'mfa_enforced' => $target->mfa_enforced,
                ],
                $actor,
                $target->administrative_unit_id
            );
        });

        if ($data['sync_with_keycloak'] ?? false) {
            $this->performKeycloakSync(
                $target->fresh(),
                $target->fresh()->getEffectiveRoleCodes(),
                $unit,
                $actor,
                false
            );
        }

        $this->bumpDashboardCacheVersion();

        return $target->fresh($this->userRelations());
    }

    public function transitionLifecycleStatus(User $target, string $newStatus, ?string $reason, User $actor, bool $syncIdp = true): User
    {
        $newStatus = strtoupper(trim($newStatus));
        if (!in_array($newStatus, User::ACCOUNT_STATUSES, true)) {
            throw ValidationException::withMessages([
                'account_status' => 'Invalid account lifecycle status.',
            ]);
        }

        if ($actor->id === $target->id && $newStatus !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('You cannot suspend, deactivate, or lock your own account.');
        }

        $oldStatus = $target->account_status ?? ($target->is_active ? User::STATUS_ACTIVE : User::STATUS_DEACTIVATED);
        $isActive = $newStatus === User::STATUS_ACTIVE;

        DB::transaction(function () use ($target, $newStatus, $isActive, $reason, $actor, $oldStatus) {
            $target->update([
                'account_status' => $newStatus,
                'is_active' => $isActive,
                'status_reason' => $reason,
                'status_changed_at' => now(),
                'status_changed_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'USER_STATUS_CHANGED',
                User::class,
                $target->id,
                ['account_status' => $oldStatus],
                ['account_status' => $newStatus, 'reason' => $reason],
                $actor,
                $target->administrative_unit_id
            );
        });

        if ($syncIdp && $target->keycloak_sub) {
            $idpResult = $this->keycloakAdmin->setUserEnabled($target->fresh(), $isActive, $reason);
            $target->update([
                'idp_sync_status' => $idpResult['idp_sync_status'] ?? ($idpResult['success'] ? User::IDP_SYNC_LINKED : User::IDP_SYNC_FAILED),
                'idp_synced_at' => $idpResult['success'] ? now() : $target->idp_synced_at,
                'idp_sync_error' => $idpResult['success'] ? null : ($idpResult['error_message'] ?? 'Keycloak status sync failed.'),
            ]);
        }

        $this->bumpDashboardCacheVersion();

        return $target->fresh($this->userRelations());
    }

    public function assignUserRoles(User $target, array $data, User $actor, bool $syncIdp = true): User
    {
        $roleCodes = $this->normalizeAndValidateRequestedRoles($data, $actor, $target);

        DB::transaction(function () use ($target, $roleCodes, $actor) {
            $oldRoles = $target->getEffectiveRoleCodes();
            $target->update([
                'role_code' => $roleCodes[0],
                'updated_by' => $actor->id,
            ]);
            $this->syncUserRoleRecords($target, $roleCodes, $actor);

            AuditLog::log(
                'USER_ROLES_UPDATED',
                User::class,
                $target->id,
                ['roles' => $oldRoles],
                ['roles' => $roleCodes],
                $actor,
                $target->administrative_unit_id
            );
        });

        if ($syncIdp && $target->keycloak_sub) {
            $idpResult = $this->keycloakAdmin->assignRoles($target->fresh(), $roleCodes);
            $target->update([
                'idp_sync_status' => $idpResult['idp_sync_status'] ?? $target->idp_sync_status,
                'idp_synced_at' => $idpResult['success'] ? now() : $target->idp_synced_at,
                'idp_sync_error' => $idpResult['success'] ? null : ($idpResult['error_message'] ?? 'Keycloak role sync failed.'),
            ]);
        }

        $this->bumpDashboardCacheVersion();

        return $target->fresh($this->userRelations());
    }

    public function assignAdministrativeScope(User $target, array $data, User $actor): UserAdministrativeScope
    {
        $unit = $this->assertUnitInActorScope($actor, $data['administrative_unit_id'], 'assigned administrative unit');
        $assignmentType = strtoupper((string) ($data['assignment_type'] ?? 'ADDITIONAL_CHARGE'));

        if (!in_array($assignmentType, UserAdministrativeScope::ASSIGNMENT_TYPES, true)) {
            throw ValidationException::withMessages([
                'assignment_type' => 'Invalid organizational scope assignment type.',
            ]);
        }

        $scope = DB::transaction(function () use ($target, $unit, $assignmentType, $data, $actor) {
            if ($assignmentType === 'PRIMARY_POSTING') {
                UserAdministrativeScope::where('user_id', $target->id)
                    ->where('assignment_type', 'PRIMARY_POSTING')
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'valid_until' => now()]);

                $target->update([
                    'administrative_unit_id' => $unit->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $scope = UserAdministrativeScope::create([
                'id' => (string) Str::uuid(),
                'user_id' => $target->id,
                'administrative_unit_id' => $unit->id,
                'assignment_type' => $assignmentType,
                'is_active' => true,
                'valid_from' => $data['valid_from'] ?? now(),
                'valid_until' => $data['valid_until'] ?? null,
                'delegated_by' => $actor->id,
                'official_order_ref' => $data['official_order_ref'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            AuditLog::log(
                'USER_SCOPE_ASSIGNED',
                User::class,
                $target->id,
                null,
                [
                    'scope_id' => $scope->id,
                    'administrative_unit_id' => $unit->id,
                    'ancestry_path' => $unit->ancestry_path,
                    'assignment_type' => $assignmentType,
                    'valid_until' => $scope->valid_until?->toIso8601String(),
                    'official_order_ref' => $scope->official_order_ref,
                ],
                $actor,
                $unit->id
            );

            return $scope;
        });

        $this->bumpDashboardCacheVersion();

        return $scope->load(['administrativeUnit', 'delegatedByUser']);
    }

    public function revokeAdministrativeScope(User $target, UserAdministrativeScope $scope, User $actor): void
    {
        if ($scope->user_id !== $target->id) {
            throw ValidationException::withMessages([
                'scope' => 'Scope assignment does not belong to the specified user.',
            ]);
        }

        if ($scope->assignment_type === 'PRIMARY_POSTING') {
            throw ValidationException::withMessages([
                'scope' => 'Cannot revoke primary posting directly. Assign a new primary posting instead.',
            ]);
        }

        DB::transaction(function () use ($target, $scope, $actor) {
            $scope->update([
                'is_active' => false,
                'valid_until' => now(),
            ]);

            AuditLog::log(
                'USER_SCOPE_REVOKED',
                User::class,
                $target->id,
                ['scope_id' => $scope->id, 'administrative_unit_id' => $scope->administrative_unit_id],
                ['is_active' => false],
                $actor,
                $scope->administrative_unit_id
            );
        });

        $this->bumpDashboardCacheVersion();
    }

    public function syncWithKeycloak(User $target, User $actor, bool $enforceMfaOnly = false): array
    {
        $unit = $target->administrativeUnit;
        $roleCodes = $target->getEffectiveRoleCodes();

        if ($enforceMfaOnly) {
            $result = $this->keycloakAdmin->enforceMfa($target, (bool) $target->mfa_enforced);
            $target->update([
                'idp_sync_status' => $result['idp_sync_status'] ?? $target->idp_sync_status,
                'idp_synced_at' => $result['success'] ? now() : $target->idp_synced_at,
                'idp_sync_error' => $result['success'] ? null : ($result['error_message'] ?? 'MFA sync failed.'),
            ]);

            AuditLog::log(
                'USER_KEYCLOAK_MFA_SYNCED',
                User::class,
                $target->id,
                null,
                ['success' => $result['success'], 'mfa_enforced' => $target->mfa_enforced],
                $actor,
                $target->administrative_unit_id
            );

            return $result;
        }

        return $this->performKeycloakSync($target, $roleCodes, $unit, $actor, false);
    }

    public function uploadPhoto(User $target, UploadedFile $file, User $actor): User
    {
        $realMime = $file->getMimeType() ?: $file->getClientMimeType();
        if (!in_array($realMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages([
                'photo' => 'Profile photograph must be a valid JPEG, PNG, or WebP image.',
            ]);
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $path = $file->storeAs("user-management/photos/{$target->id}", Str::uuid() . ".{$ext}", 'local');

        if ($target->photo_path && Storage::disk('local')->exists($target->photo_path)) {
            Storage::disk('local')->delete($target->photo_path);
        }

        $target->update([
            'photo_path' => $path,
            'photo_mime' => $realMime,
            'photo_hash' => $hash,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'USER_PHOTO_UPDATED',
            User::class,
            $target->id,
            null,
            ['photo_hash' => $hash, 'photo_mime' => $realMime],
            $actor,
            $target->administrative_unit_id
        );

        return $target->fresh($this->userRelations());
    }

    public function streamPhoto(User $target): StreamedResponse
    {
        if (!$target->photo_path || !Storage::disk('local')->exists($target->photo_path)) {
            abort(404, 'Profile photograph not found.');
        }

        return Storage::disk('local')->response($target->photo_path, 'profile-photo', [
            'Content-Type' => $target->photo_mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function getDashboardSummary(User $actor): array
    {
        $version = (int) Cache::get('user_mgmt:dashboard:version', 1);
        $scopeKey = $actor->isNationalScope()
            ? 'national'
            : md5(implode(',', $actor->getAccessibleAncestryPaths()));
        $cacheKey = "user_mgmt:dashboard:v{$version}:{$scopeKey}";

        return Cache::remember($cacheKey, 120, function () use ($actor) {
            $baseQuery = User::query()->inJurisdiction($actor);

            $totalUsers = (clone $baseQuery)->count();
            $activeUsers = (clone $baseQuery)
                ->where('is_active', true)
                ->where('account_status', User::STATUS_ACTIVE)
                ->count();
            $pendingUsers = (clone $baseQuery)->where('account_status', User::STATUS_PENDING)->count();
            $suspendedUsers = (clone $baseQuery)->where('account_status', User::STATUS_SUSPENDED)->count();
            $deactivatedUsers = (clone $baseQuery)->where('account_status', User::STATUS_DEACTIVATED)->count();
            $lockedUsers = (clone $baseQuery)->where('account_status', User::STATUS_LOCKED)->count();
            $inactiveUsers = $totalUsers - $activeUsers;

            $mfaEnforcedCount = (clone $baseQuery)->where('mfa_enforced', true)->count();
            $mfaVerifiedCount = (clone $baseQuery)->where('mfa_verified', true)->count();
            $idpLinkedCount = (clone $baseQuery)->where('idp_sync_status', User::IDP_SYNC_LINKED)->count();
            $idpFailedCount = (clone $baseQuery)->where('idp_sync_status', User::IDP_SYNC_FAILED)->count();

            $userTable = (new User())->getTable();
            $adminTable = (new AdministrativeUnit())->getTable();

            $orgLabels = Organization::all()->keyBy('id');
            $byOrganization = (clone $baseQuery)
                ->selectRaw("{$userTable}.organization_id, COUNT(*) as total, SUM(CASE WHEN {$userTable}.is_active AND {$userTable}.account_status = ? THEN 1 ELSE 0 END) as active", [User::STATUS_ACTIVE])
                ->groupBy("{$userTable}.organization_id")
                ->get()
                ->map(function ($row) use ($orgLabels) {
                    $org = $row->organization_id ? $orgLabels->get($row->organization_id) : null;
                    return [
                        'organization_id' => $row->organization_id,
                        'name_en' => $org?->name_en ?? 'Unassigned Organization',
                        'name_bn' => $org?->name_bn ?? 'সংগঠন নির্ধারিত নয়',
                        'org_type' => $org?->org_type ?? 'OTHER',
                        'total' => (int) $row->total,
                        'active' => (int) $row->active,
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->all();

            $byGeoLevel = (clone $baseQuery)
                ->leftJoin($adminTable, "{$adminTable}.id", '=', "{$userTable}.administrative_unit_id")
                ->selectRaw("COALESCE({$adminTable}.tier_level, 'UNASSIGNED') as tier_level, COUNT(*) as total, SUM(CASE WHEN {$userTable}.is_active AND {$userTable}.account_status = ? THEN 1 ELSE 0 END) as active", [User::STATUS_ACTIVE])
                ->groupBy(DB::raw("COALESCE({$adminTable}.tier_level, 'UNASSIGNED')"))
                ->get()
                ->map(fn ($row) => [
                    'tier_level' => $row->tier_level,
                    'total' => (int) $row->total,
                    'active' => (int) $row->active,
                ])
                ->values()
                ->all();

            $roleLabels = Role::all()->keyBy('code');
            $byRole = (clone $baseQuery)
                ->selectRaw("COALESCE({$userTable}.role_code, 'UNASSIGNED') as role_code, COUNT(*) as total, SUM(CASE WHEN {$userTable}.is_active AND {$userTable}.account_status = ? THEN 1 ELSE 0 END) as active", [User::STATUS_ACTIVE])
                ->groupBy(DB::raw("COALESCE({$userTable}.role_code, 'UNASSIGNED')"))
                ->get()
                ->map(function ($row) use ($roleLabels) {
                    $role = $roleLabels->get($row->role_code);
                    return [
                        'role_code' => $row->role_code,
                        'name_en' => $role?->name_en ?? $row->role_code,
                        'name_bn' => $role?->name_bn ?? $row->role_code,
                        'tier_level' => $role?->tier_level ?? 'FIELD',
                        'total' => (int) $row->total,
                        'active' => (int) $row->active,
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->all();

            $recentActivity = AuditLog::with('user')
                ->whereIn('auditable_type', [User::class, Role::class, Organization::class])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action_name' => $log->action_name,
                    'auditable_type' => class_basename($log->auditable_type),
                    'auditable_id' => $log->auditable_id,
                    'actor_name' => $log->user?->full_name_en ?? 'System',
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->all();

            return [
                'kpis' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'inactive_users' => $inactiveUsers,
                    'pending_users' => $pendingUsers,
                    'suspended_users' => $suspendedUsers,
                    'deactivated_users' => $deactivatedUsers,
                    'locked_users' => $lockedUsers,
                    'mfa_enforced_users' => $mfaEnforcedCount,
                    'mfa_verified_users' => $mfaVerifiedCount,
                    'idp_linked_users' => $idpLinkedCount,
                    'idp_failed_users' => $idpFailedCount,
                    'total_roles' => Role::where('is_active', true)->count(),
                    'total_organizations' => Organization::where('is_active', true)->count(),
                ],
                'by_organization' => $byOrganization,
                'by_geographical_level' => $byGeoLevel,
                'role_distribution' => $byRole,
                'keycloak_health' => $this->keycloakAdmin->healthCheck(),
                'recent_activity' => $recentActivity,
            ];
        });
    }

    public function assertUnitInActorScope(User $actor, string $unitId, string $context = 'administrative unit'): AdministrativeUnit
    {
        $unit = AdministrativeUnit::find($unitId);
        if (!$unit) {
            throw ValidationException::withMessages([
                'administrative_unit_id' => "Selected {$context} does not exist.",
            ]);
        }

        if (!$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
            throw new AuthorizationException("Selected {$context} lies outside your authorized administrative jurisdiction.");
        }

        return $unit;
    }

    protected function performKeycloakSync(User $user, array $roleCodes, ?AdministrativeUnit $unit, User $actor, bool $isProvision): array
    {
        $scopeProfile = $unit ? [
            'tier_level' => $unit->tier_level,
            'ancestry_path' => $unit->ancestry_path,
            'scope_id' => $unit->id,
        ] : $user->getAdministrativeScope();

        $result = $isProvision
            ? $this->keycloakAdmin->provisionUser($user, $roleCodes, $scopeProfile)
            : $this->keycloakAdmin->syncUser($user, $roleCodes, $scopeProfile);

        $user->update([
            'keycloak_sub' => $result['keycloak_sub'] ?? $user->keycloak_sub,
            'keycloak_username' => $result['keycloak_username'] ?? $user->keycloak_username,
            'idp_sync_status' => $result['idp_sync_status'] ?? ($result['success'] ? User::IDP_SYNC_LINKED : User::IDP_SYNC_FAILED),
            'idp_synced_at' => $result['success'] ? now() : $user->idp_synced_at,
            'idp_sync_error' => $result['success'] ? null : ($result['error_message'] ?? 'Keycloak synchronization error.'),
        ]);

        AuditLog::log(
            $result['success'] ? 'USER_KEYCLOAK_SYNCED' : 'USER_KEYCLOAK_SYNC_FAILED',
            User::class,
            $user->id,
            null,
            [
                'idp_sync_status' => $user->idp_sync_status,
                'is_mock' => $result['is_mock'] ?? false,
                'error_code' => $result['error_code'] ?? null,
            ],
            $actor,
            $user->administrative_unit_id
        );

        return $result;
    }

    /**
     * @return list<string>
     */
    protected function normalizeAndValidateRequestedRoles(array $data, User $actor, ?User $target = null): array
    {
        $codes = [];
        if (!empty($data['role_codes']) && is_array($data['role_codes'])) {
            $codes = array_values(array_unique(array_filter($data['role_codes'])));
        } elseif (!empty($data['role_code'])) {
            $codes = [(string) $data['role_code']];
        }

        if (empty($codes)) {
            throw ValidationException::withMessages([
                'role_codes' => 'At least one system role must be assigned.',
            ]);
        }

        $roles = Role::whereIn('code', $codes)->where('is_active', true)->get()->keyBy('code');
        foreach ($codes as $code) {
            if (!$roles->has($code)) {
                throw ValidationException::withMessages([
                    'role_codes' => "Role '{$code}' does not exist or is inactive.",
                ]);
            }
        }

        // Prevent privilege escalation & self Super Admin removal
        $actorMinRank = $this->policy->getActorMinimumHierarchyRank($actor);
        if (!$actor->hasRole('ROLE_SUPER_ADMIN')) {
            foreach ($roles as $role) {
                if ($role->code === 'ROLE_SUPER_ADMIN' || $role->hierarchy_rank < $actorMinRank) {
                    throw new AuthorizationException("You are not authorized to assign role '{$role->code}' (privilege escalation blocked).");
                }
            }
        }

        if ($target && $actor->id === $target->id && $target->hasRole('ROLE_SUPER_ADMIN') && !in_array('ROLE_SUPER_ADMIN', $codes, true)) {
            throw new AuthorizationException('You cannot remove ROLE_SUPER_ADMIN from your own account.');
        }

        return $codes;
    }

    protected function syncUserRoleRecords(User $user, array $roleCodes, User $actor): void
    {
        $roles = Role::whereIn('code', $roleCodes)->get()->keyBy('code');
        $primaryCode = $roleCodes[0];

        UserRole::where('user_id', $user->id)
            ->whereNotIn('role_id', $roles->pluck('id')->all())
            ->delete();

        foreach ($roleCodes as $code) {
            $role = $roles->get($code);
            if (!$role) {
                continue;
            }
            UserRole::updateOrCreate(
                ['user_id' => $user->id, 'role_id' => $role->id],
                [
                    'is_primary' => $code === $primaryCode,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ]
            );
        }

        $user->clearRbacMemoization();
    }

    protected function requiresMfaByTierOrRoles(string $tierLevel, array $roleCodes): bool
    {
        if (in_array(strtoupper($tierLevel), ['NATIONAL', 'DIRECTORATE', 'DIVISION', 'DISTRICT', 'UPAZILA'], true)) {
            return true;
        }

        return Role::whereIn('code', $roleCodes)->where('mfa_required', true)->exists();
    }
}
