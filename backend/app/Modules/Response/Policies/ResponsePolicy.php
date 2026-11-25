<?php

namespace App\Modules\Response\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseSituationReport;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC, 7-tier jurisdiction scoping, District/UNO/NEOC+ lifecycle authority,
 * location privacy view permissions, and ROLE_SUPER_ADMIN operational blackout for Module 05.
 */
class ResponsePolicy
{
    protected const ACTIVITY_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
    ];

    protected const VIEW_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
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
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_TEAM_LEADER',
        'ROLE_VOLUNTEER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    protected const OPERATION_CREATE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    protected const OPERATION_UPDATE_ROLES = [
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

    protected const LIFECYCLE_AUTHORITY_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
    ];

    protected const TASK_MANAGE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_TEAM_LEADER',
        'ROLE_VOLUNTEER',
    ];

    protected const COORDINATION_MANAGE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_WAREHOUSE_MANAGER',
        'ROLE_CPP_TEAM_LEADER',
    ];

    protected const REPORT_CREATE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_TAG_OFFICER',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_TEAM_LEADER',
        'ROLE_VOLUNTEER',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function manageActivities(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'response.activity.manage',
            self::ACTIVITY_MANAGE_ROLES,
            'Insufficient permission to manage configurable response activities.'
        );
    }

    public function viewAny(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'response.operation.view',
            self::VIEW_ROLES,
            'You are not authorized to view disaster response operations.'
        );
    }

    public function viewOperation(User $user, ResponseOperation $operation): Response
    {
        $roleCheck = $this->viewAny($user);
        if (!$roleCheck->allowed()) {
            return $roleCheck;
        }

        $operation->loadMissing('administrativeUnit');

        return $this->checkScope($user, $operation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function createOperation(User $user): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'create response operations')) {
            return $blackout;
        }

        return $this->allowIfHasPermissionOrRoles(
            $user,
            'response.operation.create',
            self::OPERATION_CREATE_ROLES,
            'Your role is not authorized to create response operations.'
        );
    }

    public function updateOperation(User $user, ResponseOperation $operation): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'update response operations')) {
            return $blackout;
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'response.operation.update',
            self::OPERATION_UPDATE_ROLES,
            'Your role is not authorized to update response operations.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $operation->loadMissing('administrativeUnit');

        return $this->checkScope($user, $operation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function manageLifecycle(User $user, ResponseOperation $operation, ?string $targetStatus = null): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'change response operation lifecycle states')) {
            return $blackout;
        }

        $roles = $targetStatus && in_array($targetStatus, ResponseOperation::HIGH_TIER_LIFECYCLE_TARGETS, true)
            ? self::LIFECYCLE_AUTHORITY_ROLES
            : self::OPERATION_UPDATE_ROLES;

        $permission = $targetStatus && in_array($targetStatus, ResponseOperation::HIGH_TIER_LIFECYCLE_TARGETS, true)
            ? 'response.operation.manage_lifecycle'
            : 'response.operation.update';

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            $permission,
            $roles,
            'High-tier District, UNO, or NEOC+ authority is required to activate, suspend, complete, or close operations.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $operation->loadMissing('administrativeUnit');

        return $this->checkScope($user, $operation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function manageTasks(User $user, ResponseOperation $operation): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'create or update response tasks')) {
            return $blackout;
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'response.task.manage',
            self::TASK_MANAGE_ROLES,
            'Your role is not authorized to manage response tasks.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $operation->loadMissing('administrativeUnit');

        return $this->checkScope($user, $operation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function manageCoordination(User $user, ResponseOperation $operation): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'manage agencies, teams, or resources')) {
            return $blackout;
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'response.coordination.manage',
            self::COORDINATION_MANAGE_ROLES,
            'Your role is not authorized to manage response coordination, teams, or resource links.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $operation->loadMissing('administrativeUnit');

        return $this->checkScope($user, $operation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function createReport(User $user, ResponseOperation $operation): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'submit situation or incident reports')) {
            return $blackout;
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'response.report.create',
            self::REPORT_CREATE_ROLES,
            'Your role is not authorized to submit emergency situation reports.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $operation->loadMissing('administrativeUnit');

        return $this->checkScope($user, $operation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function viewReport(User $user, ResponseSituationReport $report): Response
    {
        $roleCheck = $this->viewAny($user);
        if (!$roleCheck->allowed()) {
            return $roleCheck;
        }

        $report->loadMissing('administrativeUnit');

        return $this->checkScope($user, $report->administrativeUnit?->ancestry_path ?? '/');
    }

    private function denySuperAdmin(User $user, string $action): ?Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny(
                "Super Admin operational blackout: ROLE_SUPER_ADMIN cannot {$action}."
            );
        }

        return null;
    }

    private function checkScope(User $user, string $targetAncestryPath): Response
    {
        if ($this->scopeService->canAccessScope($user, $targetAncestryPath)) {
            return Response::allow();
        }

        return Response::deny('Jurisdiction access denied: response record falls outside your administrative hierarchy.');
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
