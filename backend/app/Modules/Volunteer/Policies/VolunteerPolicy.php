<?php

namespace App\Modules\Volunteer\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerAttendance;
use App\Modules\Volunteer\Models\VolunteerCategory;
use App\Modules\Volunteer\Models\VolunteerDeployment;
use App\Modules\Volunteer\Models\VolunteerOrganization;
use App\Modules\Volunteer\Models\VolunteerTrainingProgram;
use App\Modules\Volunteer\Models\VolunteerTrainingSession;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC, 7-tier administrative jurisdiction scoping, 4-eye Separation of Duties (SoD),
 * PII & GPS privacy controls, external partner anonymization, and ROLE_SUPER_ADMIN operational blackout.
 */
class VolunteerPolicy
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
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
        'ROLE_CPP_TEAM_LEADER',
        'ROLE_VOLUNTEER_RESPONDER',
        'ROLE_VOLUNTEER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    public const CATEGORY_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
    ];

    public const ORGANIZATION_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    public const ORGANIZATION_VERIFY_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    public const VOLUNTEER_WRITE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_CPP_LEADER',
        'ROLE_CPP_TEAM_LEADER',
    ];

    public const VOLUNTEER_DELETE_ROLES = [
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    public const VERIFY_APPROVE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_TAG_OFFICER',
    ];

    public const DEPLOY_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
        'ROLE_CPP_TEAM_LEADER',
    ];

    public const ATTENDANCE_RECORD_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
        'ROLE_CPP_TEAM_LEADER',
        'ROLE_VOLUNTEER_RESPONDER',
        'ROLE_VOLUNTEER',
    ];

    public const SUPERVISOR_CONFIRM_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
        'ROLE_CPP_TEAM_LEADER',
    ];

    public const TRAINING_PROGRAM_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    public const TRAINING_MANAGE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_CPP_LEADER',
        'ROLE_CPP_TEAM_LEADER',
    ];

    public const PII_DECRYPT_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    public const EXPORT_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_NATIONAL_AUDITOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_CPP_LEADER',
        'ROLE_CPP_TEAM_LEADER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function isExternalPartnerOnly(User $user): bool
    {
        return $user->hasRole('ROLE_EXTERNAL_PARTNER')
            && !$this->hasAnyRole($user, array_values(array_diff(self::VIEW_ROLES, ['ROLE_EXTERNAL_PARTNER'])));
    }

    public function canViewExactLocation(User $user): bool
    {
        if ($this->isExternalPartnerOnly($user)) {
            return false;
        }

        return $this->hasAnyRole($user, [
            'ROLE_SUPER_ADMIN',
            'ROLE_MINISTRY_EXECUTIVE',
            'ROLE_NEOC_DIRECTOR',
            'ROLE_NATIONAL_AUDITOR',
            'ROLE_DDM_DIRECTOR_GENERAL',
            'ROLE_DIVISIONAL_COMMISSIONER',
            'ROLE_DEPUTY_COMMISSIONER',
            'ROLE_DRRO',
            'ROLE_UNO',
            'ROLE_PIO',
            'ROLE_SHELTER_MANAGER',
            'ROLE_CPP_LEADER',
            'ROLE_CPP_TEAM_LEADER',
        ]) || $user->hasPermission('volunteer.location.view_exact');
    }

    public function viewAny(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.roster.view',
            self::VIEW_ROLES,
            'You are not authorized to view volunteer management records.'
        );
    }

    public function viewVolunteer(User $user, Volunteer $volunteer): Response
    {
        $perm = $this->viewAny($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        if ($volunteer->user_id && $volunteer->user_id === $user->id) {
            return Response::allow();
        }

        $volunteer->loadMissing('administrativeUnit');
        return $this->checkScope($user, $volunteer->administrativeUnit?->ancestry_path ?? '/');
    }

    public function manageCategory(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.category.manage',
            self::CATEGORY_MANAGE_ROLES,
            'You are not authorized to manage volunteer categories or skills.'
        );
    }

    public function manageSkill(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.skill.manage',
            array_unique(array_merge(self::CATEGORY_MANAGE_ROLES, ['ROLE_DRRO', 'ROLE_PIO', 'ROLE_CPP_LEADER', 'ROLE_CPP_TEAM_LEADER'])),
            'You are not authorized to manage volunteer skill catalog.'
        );
    }

    public function manageOrganization(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.organization.manage',
            self::ORGANIZATION_MANAGE_ROLES,
            'You are not authorized to manage volunteer organizations.'
        );
    }

    public function viewOrganization(User $user, VolunteerOrganization $organization): Response
    {
        $perm = $this->viewAny($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        $organization->loadMissing('administrativeUnit');
        return $this->checkScope($user, $organization->administrativeUnit?->ancestry_path ?? '/');
    }

    public function verifyOrganization(User $user, VolunteerOrganization $organization): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot verify volunteer organizations.');
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.organization.verify',
            self::ORGANIZATION_VERIFY_ROLES,
            'Your role is not authorized to verify volunteer organizations.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $organization->loadMissing('administrativeUnit');
        return $this->checkScope($user, $organization->administrativeUnit?->ancestry_path ?? '/');
    }

    public function createVolunteer(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot register operational volunteers.');
        }

        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.roster.register',
            self::VOLUNTEER_WRITE_ROLES,
            'Your role is not authorized to register volunteers.'
        );
    }

    public function updateVolunteer(User $user, Volunteer $volunteer): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot modify operational volunteer profiles.');
        }

        $isSelf = $volunteer->user_id && $volunteer->user_id === $user->id;
        if ($isSelf && $this->hasAnyRole($user, ['ROLE_VOLUNTEER', 'ROLE_VOLUNTEER_RESPONDER'])) {
            return Response::allow();
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.roster.update',
            self::VOLUNTEER_WRITE_ROLES,
            'Your role is not authorized to update volunteer profiles.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $volunteer->loadMissing('administrativeUnit');
        return $this->checkScope($user, $volunteer->administrativeUnit?->ancestry_path ?? '/');
    }

    public function deleteVolunteer(User $user, Volunteer $volunteer): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot delete volunteer profiles.');
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.roster.update',
            self::VOLUNTEER_DELETE_ROLES,
            'Only DRRO, UNO, or PIO may archive or remove volunteer records.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $volunteer->loadMissing('administrativeUnit');
        return $this->checkScope($user, $volunteer->administrativeUnit?->ancestry_path ?? '/');
    }

    public function submitVolunteer(User $user, Volunteer $volunteer): Response
    {
        return $this->updateVolunteer($user, $volunteer);
    }

    public function verifyOrApproveVolunteer(User $user, Volunteer $volunteer): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot verify or approve volunteers.');
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.roster.verify',
            self::VERIFY_APPROVE_ROLES,
            'Your role is not authorized to verify or approve volunteers.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $volunteer->loadMissing('administrativeUnit');
        $scope = $this->checkScope($user, $volunteer->administrativeUnit?->ancestry_path ?? '/');
        if (!$scope->allowed()) {
            return $scope;
        }

        // 4-eye Separation of Duties
        if (
            ($volunteer->submitted_by && $volunteer->submitted_by === $user->id)
            || ($volunteer->created_by && $volunteer->created_by === $user->id && !$volunteer->submitted_by)
        ) {
            return Response::deny('4-eye Separation of Duties violation: the registering/submitting officer cannot verify or approve the same volunteer.');
        }

        return Response::allow();
    }

    public function decryptPii(User $user, Volunteer $volunteer): Response
    {
        if ($this->isExternalPartnerOnly($user)) {
            return Response::deny('External partners are strictly prohibited from accessing volunteer PII.');
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.pii.decrypt',
            self::PII_DECRYPT_ROLES,
            'Your role is not authorized to reveal protected volunteer PII.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $volunteer->loadMissing('administrativeUnit');
        return $this->checkScope($user, $volunteer->administrativeUnit?->ancestry_path ?? '/');
    }

    public function deploy(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot deploy volunteers.');
        }

        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.deployment.dispatch',
            self::DEPLOY_ROLES,
            'Your role is not authorized to deploy volunteers.'
        );
    }

    public function manageDeployment(User $user, VolunteerDeployment $deployment): Response
    {
        $perm = $this->deploy($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        $deployment->loadMissing('administrativeUnit');
        return $this->checkScope($user, $deployment->administrativeUnit?->ancestry_path ?? '/');
    }

    public function viewDeployment(User $user, VolunteerDeployment $deployment): Response
    {
        $perm = $this->viewAny($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        $deployment->loadMissing(['volunteer', 'administrativeUnit']);
        if ($deployment->volunteer?->user_id === $user->id) {
            return Response::allow();
        }

        return $this->checkScope($user, $deployment->administrativeUnit?->ancestry_path ?? '/');
    }

    public function recordAttendance(User $user, VolunteerDeployment $deployment): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot record volunteer attendance.');
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.attendance.record',
            self::ATTENDANCE_RECORD_ROLES,
            'Your role is not authorized to record volunteer check-in or check-out.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $deployment->loadMissing(['volunteer', 'administrativeUnit']);

        // Self-service allowed for assigned volunteer
        if ($deployment->volunteer?->user_id === $user->id) {
            return Response::allow();
        }

        if ($this->hasAnyRole($user, ['ROLE_VOLUNTEER', 'ROLE_VOLUNTEER_RESPONDER'])
            && !$this->hasAnyRole($user, self::DEPLOY_ROLES)
        ) {
            return Response::deny('Volunteers may only record attendance for deployments assigned to them.');
        }

        return $this->checkScope($user, $deployment->administrativeUnit?->ancestry_path ?? '/');
    }

    public function confirmAttendance(User $user, VolunteerAttendance $attendance): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot confirm volunteer attendance.');
        }

        $perm = $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.attendance.confirm',
            self::SUPERVISOR_CONFIRM_ROLES,
            'Your role is not authorized to confirm volunteer attendance.'
        );
        if (!$perm->allowed()) {
            return $perm;
        }

        $attendance->loadMissing(['volunteer', 'administrativeUnit']);
        $scope = $this->checkScope($user, $attendance->administrativeUnit?->ancestry_path ?? '/');
        if (!$scope->allowed()) {
            return $scope;
        }

        if ($attendance->recorded_by === $user->id || $attendance->volunteer?->user_id === $user->id) {
            return Response::deny('4-eye Separation of Duties violation: a volunteer or recorder cannot confirm their own attendance.');
        }

        return Response::allow();
    }

    public function manageTrainingProgram(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.training.manage',
            self::TRAINING_PROGRAM_ROLES,
            'Your role is not authorized to manage volunteer training programs.'
        );
    }

    public function manageTrainingSession(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin operational blackout: ROLE_SUPER_ADMIN cannot manage operational training sessions or certificates.');
        }

        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.training.manage',
            self::TRAINING_MANAGE_ROLES,
            'Your role is not authorized to manage volunteer training sessions or certificates.'
        );
    }

    public function viewTrainingSession(User $user, VolunteerTrainingSession $session): Response
    {
        $perm = $this->viewAny($user);
        if (!$perm->allowed()) {
            return $perm;
        }

        $session->loadMissing('administrativeUnit');
        return $this->checkScope($user, $session->administrativeUnit?->ancestry_path ?? '/');
    }

    public function export(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'volunteer.roster.export',
            self::EXPORT_ROLES,
            'Your role is not authorized to export volunteer reports.'
        );
    }

    protected function checkScope(User $user, string $ancestryPath): Response
    {
        if ($this->scopeService->canAccessScope($user, $ancestryPath)) {
            return Response::allow();
        }

        return Response::deny('Record belongs to an administrative jurisdiction outside your assigned scope.');
    }

    protected function allowIfHasPermissionOrRoles(
        User $user,
        string $permission,
        array $roles,
        string $denyMessage
    ): Response {
        if ($this->hasAnyRole($user, $roles) || $user->hasPermission($permission)) {
            return Response::allow();
        }

        return Response::deny($denyMessage);
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
