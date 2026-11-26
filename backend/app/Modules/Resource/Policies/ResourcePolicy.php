<?php

namespace App\Modules\Resource\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Resource\Models\HumanResourceDeployment;
use App\Modules\Resource\Models\ResourceAllocation;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceMaintenance;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC, 7-tier administrative jurisdiction, external partner anonymization,
 * and ROLE_SUPER_ADMIN operational blackout for deployments in Module 07 (Resource Management).
 */
class ResourcePolicy
{
    protected const VIEW_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_TAG_OFFICER',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_TEAM_LEADER',
        'ROLE_CPP_LEADER',
        'ROLE_VOLUNTEER',
        'ROLE_VOLUNTEER_RESPONDER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    protected const CATEGORY_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
    ];

    protected const ASSET_WRITE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
    ];

    protected const DEPLOY_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    protected const EXPORT_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'resource.asset.view',
            self::VIEW_ROLES,
            'You are not authorized to view disaster management resources.'
        );
    }

    public function viewAsset(User $user, ResourceAsset $asset): Response
    {
        $perm = $this->viewAny($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        $asset->loadMissing('administrativeUnit');

        return $this->checkScope($user, $asset->administrativeUnit?->ancestry_path ?? '/');
    }

    public function manageCategory(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'resource.category.manage',
            self::CATEGORY_MANAGE_ROLES,
            'You do not have permission to manage configurable resource categories.'
        );
    }

    public function createAsset(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'resource.asset.create',
            self::ASSET_WRITE_ROLES,
            'Your role is not authorized to register disaster management resources.'
        );
    }

    public function updateAsset(User $user, ResourceAsset $asset): Response
    {
        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'resource.asset.update',
            self::ASSET_WRITE_ROLES,
            'Your role is not authorized to update resource records or maintenance logs.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $asset->loadMissing('administrativeUnit');

        return $this->checkScope($user, $asset->administrativeUnit?->ancestry_path ?? '/');
    }

    public function deleteAsset(User $user, ResourceAsset $asset): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot delete operational resource assets.');
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'resource.asset.update',
            ['ROLE_MINISTRY_EXECUTIVE', 'ROLE_NEOC_DIRECTOR', 'ROLE_DDM_DIRECTOR_GENERAL', 'ROLE_DDM_RELIEF_DIRECTOR', 'ROLE_DRRO', 'ROLE_PIO'],
            'Your role is not authorized to retire or remove resources.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $asset->loadMissing('administrativeUnit');

        return $this->checkScope($user, $asset->administrativeUnit?->ancestry_path ?? '/');
    }

    public function manageMaintenance(User $user, ResourceAsset $asset): Response
    {
        return $this->updateAsset($user, $asset);
    }

    public function viewMaintenance(User $user, ResourceMaintenance $maintenance): Response
    {
        $perm = $this->viewAny($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        $maintenance->loadMissing('administrativeUnit');

        return $this->checkScope($user, $maintenance->administrativeUnit?->ancestry_path ?? '/');
    }

    public function deployResource(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot allocate or deploy emergency resources.');
        }

        return $this->allowIfHasPermissionOrRoles(
            $user,
            'resource.asset.deploy',
            self::DEPLOY_ROLES,
            'Your role is not authorized to allocate or release disaster resources.'
        );
    }

    public function manageAllocation(User $user, ResourceAllocation $allocation): Response
    {
        $perm = $this->deployResource($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        $allocation->loadMissing('administrativeUnit');

        return $this->checkScope($user, $allocation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function manageHumanDeployment(User $user, HumanResourceDeployment $deployment): Response
    {
        $perm = $this->deployResource($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        $deployment->loadMissing('administrativeUnit');

        return $this->checkScope($user, $deployment->administrativeUnit?->ancestry_path ?? '/');
    }

    public function exportResources(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'resource.asset.export',
            self::EXPORT_ROLES,
            'Your role is not authorized to export resource registers.'
        );
    }

    private function checkScope(User $user, string $targetAncestryPath): Response
    {
        if ($this->scopeService->canAccessScope($user, $targetAncestryPath)) {
            return Response::allow();
        }

        return Response::deny('Jurisdiction access denied: resource record falls outside your administrative hierarchy.');
    }

    private function allowIfHasPermissionOrRoles(User $user, string $permission, array $roles, string $denyMessage): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return in_array('ROLE_SUPER_ADMIN', $roles, true)
                ? Response::allow()
                : Response::deny($denyMessage);
        }

        if ($user->hasPermission($permission)) {
            return Response::allow();
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return Response::allow();
            }
        }

        return Response::deny($denyMessage);
    }
}
