<?php

namespace App\Modules\Workflow\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Workflow\Models\WorkflowDelegation;
use App\Modules\Workflow\Models\WorkflowInstance;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC (ROLE_PERMISSION_MATRIX.md Module 11), 7-tier administrative jurisdiction scoping,
 * ROLE_SUPER_ADMIN operational blackout on workflow transitions, and delegation authorization.
 */
class WorkflowPolicy
{
    public const VIEW_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_DIRECTOR',
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
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_SHELTER_MANAGER',
    ];

    public const TRANSITION_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_DIRECTOR',
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
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_SHELTER_MANAGER',
    ];

    public const CONFIG_ROLES = [
        'ROLE_SUPER_ADMIN',
    ];

    public const ESCALATION_TRIGGER_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function viewAny(User $user): Response
    {
        if ($user->hasPermission('workflow.instance.view') || $this->hasAnyRole($user, self::VIEW_ROLES)) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view workflow instances.');
    }

    public function viewInstance(User $user, WorkflowInstance $instance): Response
    {
        if (!$this->viewAny($user)->allowed()) {
            return Response::deny('You do not have permission to view this workflow instance.');
        }

        if ($user->isNationalScope()) {
            return Response::allow();
        }

        $unit = $instance->administrativeUnit;
        if ($unit && $this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
            return Response::allow();
        }

        // Also allow if the user is directly assigned to a task or delegated
        $isDirectlyAssigned = $instance->assigned_to_user_id === $user->id
            || $instance->submitted_by === $user->id
            || $instance->tasks()->where('assignee_user_id', $user->id)->exists();

        return $isDirectlyAssigned
            ? Response::allow()
            : Response::deny('Workflow instance lies outside your administrative jurisdiction.');
    }

    public function manageConfiguration(User $user): Response
    {
        if ($this->hasAnyRole($user, self::CONFIG_ROLES) || $user->hasPermission('workflow.configuration.manage')) {
            return Response::allow();
        }

        return Response::deny('Only authorized system administrators may manage workflow configurations.');
    }

    public function submitInstance(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from initiating operational workflow approvals.');
        }

        if ($user->hasRole('ROLE_NATIONAL_AUDITOR') || $user->hasRole('ROLE_EXTERNAL_PARTNER')) {
            return Response::deny('Read-only roles cannot initiate operational workflow approvals.');
        }

        if ($this->hasAnyRole($user, self::TRANSITION_ROLES) || $user->hasPermission('workflow.transition.execute')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to submit workflow requests.');
    }

    public function executeTransition(User $user, WorkflowInstance $instance): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from executing operational workflow approval transitions.');
        }

        if ($user->hasRole('ROLE_NATIONAL_AUDITOR') || $user->hasRole('ROLE_EXTERNAL_PARTNER')) {
            return Response::deny('Read-only roles cannot execute workflow transitions.');
        }

        if (!$this->hasAnyRole($user, self::TRANSITION_ROLES) && !$user->hasPermission('workflow.transition.execute')) {
            return Response::deny('You do not have permission to execute workflow transitions.');
        }

        return $this->viewInstance($user, $instance);
    }

    public function manageDelegation(User $user, ?WorkflowDelegation $delegation = null): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::allow();
        }

        if (!$this->hasAnyRole($user, self::TRANSITION_ROLES)) {
            return Response::deny('You are not authorized to configure workflow delegations.');
        }

        if ($delegation && $delegation->delegator_user_id !== $user->id) {
            return Response::deny('You may only manage your own workflow delegations.');
        }

        return Response::allow();
    }

    public function runEscalations(User $user): Response
    {
        return $this->hasAnyRole($user, self::ESCALATION_TRIGGER_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to trigger workflow escalation sweeps.');
    }

    protected function hasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }
        return false;
    }
}
