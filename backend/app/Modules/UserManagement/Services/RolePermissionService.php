<?php

namespace App\Modules\UserManagement\Services;

use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Policies\UserManagementPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    public function __construct(
        protected UserManagementPolicy $policy
    ) {}

    public function createRole(array $data, User $actor): Role
    {
        $code = strtoupper(trim((string) $data['code']));
        if (!str_starts_with($code, 'ROLE_')) {
            $code = 'ROLE_' . $code;
        }

        if (Role::withTrashed()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'A role with this code already exists.',
            ]);
        }

        $tierLevel = strtoupper((string) $data['tier_level']);
        $rank = (int) ($data['hierarchy_rank'] ?? (Role::TIER_RANKS[$tierLevel] ?? 7));

        $actorMinRank = $this->policy->getActorMinimumHierarchyRank($actor);
        if (!$actor->hasRole('ROLE_SUPER_ADMIN') && $rank < $actorMinRank) {
            throw new AuthorizationException('Cannot create a role with higher hierarchy privilege than your own.');
        }

        return DB::transaction(function () use ($data, $code, $tierLevel, $rank, $actor) {
            $role = Role::create([
                'id' => (string) Str::uuid(),
                'code' => $code,
                'name_en' => $data['name_en'],
                'name_bn' => $data['name_bn'],
                'tier_level' => $tierLevel,
                'hierarchy_rank' => $rank,
                'description' => $data['description'] ?? null,
                'is_system' => false,
                'is_active' => $data['is_active'] ?? true,
                'mfa_required' => (bool) ($data['mfa_required'] ?? ($rank <= 5)),
                'created_by' => $actor->id,
            ]);

            if (!empty($data['permission_ids'])) {
                $this->applyPermissionsToRole($role, $data['permission_ids'], $actor);
            }

            AuditLog::log(
                'ROLE_CREATED',
                Role::class,
                $role->id,
                null,
                [
                    'code' => $role->code,
                    'tier_level' => $role->tier_level,
                    'hierarchy_rank' => $role->hierarchy_rank,
                ],
                $actor
            );

            $this->invalidatePermissionCache($role->code);

            return $role->load('permissions');
        });
    }

    public function updateRole(Role $role, array $data, User $actor): Role
    {
        return DB::transaction(function () use ($role, $data, $actor) {
            $oldValues = [
                'name_en' => $role->name_en,
                'name_bn' => $role->name_bn,
                'tier_level' => $role->tier_level,
                'hierarchy_rank' => $role->hierarchy_rank,
                'is_active' => $role->is_active,
                'mfa_required' => $role->mfa_required,
            ];

            $tierLevel = strtoupper((string) ($data['tier_level'] ?? $role->tier_level));
            $rank = (int) ($data['hierarchy_rank'] ?? $role->hierarchy_rank);

            if (!$actor->hasRole('ROLE_SUPER_ADMIN') && $rank < $this->policy->getActorMinimumHierarchyRank($actor)) {
                throw new AuthorizationException('Cannot elevate a role above your own administrative rank.');
            }

            if ($role->code === 'ROLE_SUPER_ADMIN' && isset($data['is_active']) && !$data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => 'ROLE_SUPER_ADMIN cannot be deactivated.',
                ]);
            }

            $role->update([
                'name_en' => $data['name_en'] ?? $role->name_en,
                'name_bn' => $data['name_bn'] ?? $role->name_bn,
                'tier_level' => $role->is_system ? $role->tier_level : $tierLevel,
                'hierarchy_rank' => $role->is_system ? $role->hierarchy_rank : $rank,
                'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $role->is_active,
                'mfa_required' => array_key_exists('mfa_required', $data) ? (bool) $data['mfa_required'] : $role->mfa_required,
                'updated_by' => $actor->id,
            ]);

            if (array_key_exists('permission_ids', $data) && is_array($data['permission_ids'])) {
                $this->applyPermissionsToRole($role, $data['permission_ids'], $actor);
            }

            AuditLog::log(
                'ROLE_UPDATED',
                Role::class,
                $role->id,
                $oldValues,
                [
                    'name_en' => $role->name_en,
                    'name_bn' => $role->name_bn,
                    'tier_level' => $role->tier_level,
                    'is_active' => $role->is_active,
                    'mfa_required' => $role->mfa_required,
                ],
                $actor
            );

            $this->invalidatePermissionCache($role->code);

            return $role->fresh(['permissions']);
        });
    }

    public function syncRolePermissions(Role $role, array $permissionIdentifiers, User $actor): Role
    {
        return DB::transaction(function () use ($role, $permissionIdentifiers, $actor) {
            $oldCodes = $role->permissions()->pluck('code')->sort()->values()->all();
            $this->applyPermissionsToRole($role, $permissionIdentifiers, $actor);
            $newCodes = $role->permissions()->pluck('code')->sort()->values()->all();

            AuditLog::log(
                'ROLE_PERMISSIONS_UPDATED',
                Role::class,
                $role->id,
                ['role_code' => $role->code, 'permissions' => $oldCodes],
                ['role_code' => $role->code, 'permissions' => $newCodes],
                $actor
            );

            $this->invalidatePermissionCache($role->code);

            return $role->fresh(['permissions']);
        });
    }

    public function deleteRole(Role $role, User $actor): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'Built-in statutory system roles cannot be deleted.',
            ]);
        }

        if ($role->userRoles()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Cannot delete a role currently assigned to users. Reassign users first.',
            ]);
        }

        DB::transaction(function () use ($role, $actor) {
            AuditLog::log(
                'ROLE_DELETED',
                Role::class,
                $role->id,
                ['code' => $role->code],
                null,
                $actor
            );
            $role->delete();
            $this->invalidatePermissionCache($role->code);
        });
    }

    public function invalidatePermissionCache(string $roleCode): void
    {
        Cache::forget("rbac:role_permissions:{$roleCode}");
    }

    protected function applyPermissionsToRole(Role $role, array $permissionIdentifiers, User $actor): void
    {
        $permissions = Permission::whereIn('id', $permissionIdentifiers)
            ->orWhereIn('code', $permissionIdentifiers)
            ->get();

        // Prevent non-Super Admin from granting permissions they do not possess
        if (!$actor->hasRole('ROLE_SUPER_ADMIN')) {
            $actorPermissions = $actor->getEffectivePermissions();
            foreach ($permissions as $perm) {
                if (!in_array($perm->code, $actorPermissions, true)) {
                    throw new AuthorizationException("Cannot grant permission '{$perm->code}' that you do not hold (privilege escalation blocked).");
                }
            }
        }

        $syncPayload = [];
        $now = now();
        foreach ($permissions as $perm) {
            $syncPayload[$perm->id] = ['created_at' => $now];
        }

        $role->permissions()->sync($syncPayload);
    }
}
