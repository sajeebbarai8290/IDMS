<?php

namespace App\Modules\Project\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectCategory;
use App\Modules\Project\Models\ProjectDisbursement;
use App\Modules\Project\Models\ProjectMilestone;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC, 7-tier administrative scoping, 4-eye Separation of Duties (SoD),
 * external partner privacy masking, and ROLE_SUPER_ADMIN operational blackout for Module 06.
 */
class ProjectPolicy
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
        'ROLE_EXTERNAL_PARTNER',
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
        'ROLE_TAG_OFFICER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    protected const CATEGORY_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
    ];

    protected const SCHEME_WRITE_ROLES = [
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DRRO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
    ];

    protected const SCHEME_APPROVE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_UNO',
    ];

    protected const MILESTONE_UPDATE_ROLES = [
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DRRO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_TAG_OFFICER',
    ];

    protected const MILESTONE_CERTIFY_ROLES = [
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_TAG_OFFICER',
    ];

    protected const DISBURSEMENT_CREATE_ROLES = [
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DRRO',
        'ROLE_PIO',
    ];

    protected const DISBURSEMENT_APPROVE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
    ];

    protected const MONITORING_CREATE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_TAG_OFFICER',
        'ROLE_FIELD_ENUMERATOR',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->hasAnyRole($user, self::VIEW_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to view disaster management projects.');
    }

    public function viewProject(User $user, Project $project): Response
    {
        if (!$this->hasAnyRole($user, self::VIEW_ROLES)) {
            return Response::deny('You do not have permission to view this project.');
        }

        return $this->assertInScope($user, $project);
    }

    public function exportProjects(User $user): Response
    {
        return $this->hasAnyRole($user, self::EXPORT_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to export project reports.');
    }

    public function manageCategory(User $user): Response
    {
        return $this->hasAnyRole($user, self::CATEGORY_MANAGE_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to manage project categories.');
    }

    public function createProject(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from creating operational projects.');
        }

        return $this->hasAnyRole($user, self::SCHEME_WRITE_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to register projects.');
    }

    public function updateProject(User $user, Project $project): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from modifying operational projects.');
        }

        if (!$this->hasAnyRole($user, self::SCHEME_WRITE_ROLES)) {
            return Response::deny('You do not have permission to update projects.');
        }

        return $this->assertInScope($user, $project);
    }

    public function submitForReview(User $user, Project $project): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from submitting operational projects.');
        }

        if (!$this->hasAnyRole($user, array_merge(self::SCHEME_WRITE_ROLES, self::SCHEME_APPROVE_ROLES))) {
            return Response::deny('You do not have permission to submit projects for review.');
        }

        return $this->assertInScope($user, $project);
    }

    public function approveProject(User $user, Project $project): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from approving or managing project lifecycle.');
        }

        if (!$this->hasAnyRole($user, self::SCHEME_APPROVE_ROLES)) {
            return Response::deny('You do not have authority to approve, suspend, or close projects.');
        }

        $scopeCheck = $this->assertInScope($user, $project);
        if (!$scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($project->status === Project::STATUS_UNDER_REVIEW && $project->created_by === $user->id) {
            return Response::deny('4-Eye Separation of Duties: Project creator cannot approve their own project.');
        }

        return Response::allow();
    }

    public function executeLifecycle(User $user, Project $project): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from transitioning operational project lifecycles.');
        }

        $allowed = array_merge(self::SCHEME_APPROVE_ROLES, ['ROLE_DRRO', 'ROLE_PIO']);
        if (!$this->hasAnyRole($user, $allowed)) {
            return Response::deny('You do not have permission to update project execution status.');
        }

        return $this->assertInScope($user, $project);
    }

    public function updateMilestone(User $user, Project $project): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from modifying project milestones.');
        }

        if (!$this->hasAnyRole($user, self::MILESTONE_UPDATE_ROLES)) {
            return Response::deny('You do not have permission to manage or update project milestones.');
        }

        return $this->assertInScope($user, $project);
    }

    public function certifyMilestone(User $user, ProjectMilestone $milestone): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from certifying project milestones.');
        }

        if (!$this->hasAnyRole($user, self::MILESTONE_CERTIFY_ROLES)) {
            return Response::deny('You do not have permission to certify project milestones.');
        }

        $scopeCheck = $this->assertInScope($user, $milestone->project);
        if (!$scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($milestone->updated_by && $milestone->updated_by === $user->id) {
            return Response::deny('4-Eye Separation of Duties: Officer who updated milestone progress cannot certify it.');
        }

        return Response::allow();
    }

    public function createDisbursement(User $user, Project $project): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from recording financial transactions.');
        }

        if (!$this->hasAnyRole($user, self::DISBURSEMENT_CREATE_ROLES)) {
            return Response::deny('You do not have permission to record project financial entries.');
        }

        return $this->assertInScope($user, $project);
    }

    public function approveDisbursement(User $user, ProjectDisbursement $disbursement): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from authorizing financial records.');
        }

        if (!$this->hasAnyRole($user, self::DISBURSEMENT_APPROVE_ROLES)) {
            return Response::deny('You do not have permission to authorize project financial records.');
        }

        $scopeCheck = $this->assertInScope($user, $disbursement->project);
        if (!$scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($disbursement->created_by === $user->id) {
            return Response::deny('4-Eye Separation of Duties: Preparer cannot approve their own financial voucher.');
        }

        return Response::allow();
    }

    public function createMonitoring(User $user, Project $project): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from submitting operational monitoring reports or risks.');
        }

        if (!$this->hasAnyRole($user, self::MONITORING_CREATE_ROLES)) {
            return Response::deny('You do not have permission to submit monitoring reports or risks.');
        }

        return $this->assertInScope($user, $project);
    }

    public function downloadInternalAttachment(User $user, Project $project): Response
    {
        if ($user->hasRole('ROLE_EXTERNAL_PARTNER') && !$this->hasAnyRole($user, ['ROLE_MINISTRY_EXECUTIVE', 'ROLE_NATIONAL_AUDITOR', 'ROLE_DDM_DIRECTOR_GENERAL', 'ROLE_DDM_PLANNING_OFFICER', 'ROLE_DDM_PLANNING_DIRECTOR'])) {
            return Response::deny('External observers cannot download internal financial or monitoring attachments.');
        }

        return $this->viewProject($user, $project);
    }

    protected function assertInScope(User $user, Project $project): Response
    {
        if ($user->isNationalScope()) {
            return Response::allow();
        }

        $project->loadMissing('administrativeUnit');
        $path = $project->administrativeUnit?->ancestry_path ?? '/';

        return $this->scopeService->canAccessScope($user, $path)
            ? Response::allow()
            : Response::deny('Access denied: Project falls outside your administrative jurisdiction.');
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
