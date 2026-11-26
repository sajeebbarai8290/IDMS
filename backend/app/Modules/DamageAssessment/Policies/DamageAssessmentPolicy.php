<?php

namespace App\Modules\DamageAssessment\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC, 7-tier administrative jurisdiction scoping, 4-eye Separation of Duties (SoD),
 * external partner anonymization, and ROLE_SUPER_ADMIN operational blackout for Module 09.
 */
class DamageAssessmentPolicy
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
        'ROLE_EXTERNAL_PARTNER',
    ];

    public const WRITE_ROLES = [
        'ROLE_DRRO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
    ];

    public const DELETE_ROLES = [
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
    ];

    public const SUBMIT_ROLES = [
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
    ];

    public const VERIFY_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_TAG_OFFICER',
    ];

    public const APPROVE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
    ];

    public const EXPORT_ROLES = [
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
        'ROLE_EXTERNAL_PARTNER',
    ];

    public const CATEGORY_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DDM_PLANNING_OFFICER',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->hasAnyRole($user, self::VIEW_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to view damage assessments.');
    }

    public function viewAssessment(User $user, DamageAssessment $assessment): Response
    {
        if (!$this->hasAnyRole($user, self::VIEW_ROLES)) {
            return Response::deny('You do not have permission to view this damage assessment.');
        }

        if ($this->isExternalPartnerOnly($user) && !in_array($assessment->verification_status, DamageAssessment::REPORTABLE_STATUSES, true)) {
            return Response::deny('External partners can only view verified or approved damage assessments.');
        }

        return $this->assertInScope($user, $assessment);
    }

    public function createAssessment(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from creating operational damage assessments.');
        }

        return $this->hasAnyRole($user, self::WRITE_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to create damage assessments.');
    }

    public function updateAssessment(User $user, DamageAssessment $assessment): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from modifying operational damage assessments.');
        }

        if (!$this->hasAnyRole($user, self::WRITE_ROLES)) {
            return Response::deny('You do not have permission to update damage assessments.');
        }

        return $this->assertInScope($user, $assessment);
    }

    public function deleteAssessment(User $user, DamageAssessment $assessment): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from deleting operational damage assessments.');
        }

        if (!$this->hasAnyRole($user, self::DELETE_ROLES)) {
            return Response::deny('You do not have permission to delete damage assessments.');
        }

        return $this->assertInScope($user, $assessment);
    }

    public function submitAssessment(User $user, DamageAssessment $assessment): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from submitting operational damage assessments.');
        }

        if (!$this->hasAnyRole($user, self::SUBMIT_ROLES)) {
            return Response::deny('You do not have permission to submit damage assessments.');
        }

        return $this->assertInScope($user, $assessment);
    }

    public function verifyAssessment(User $user, DamageAssessment $assessment): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from verifying or rejecting damage assessments.');
        }

        if (!$this->hasAnyRole($user, self::VERIFY_ROLES)) {
            return Response::deny('You do not have authority to verify or request corrections on damage assessments.');
        }

        $scopeCheck = $this->assertInScope($user, $assessment);
        if (!$scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($assessment->created_by === $user->id || ($assessment->submitted_by && $assessment->submitted_by === $user->id)) {
            return Response::deny('4-Eye Separation of Duties: Field assessor/submitter cannot verify or reject their own assessment.');
        }

        return Response::allow();
    }

    public function approveAssessment(User $user, DamageAssessment $assessment): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is prohibited from approving operational damage assessments.');
        }

        if (!$this->hasAnyRole($user, self::APPROVE_ROLES)) {
            return Response::deny('You do not have authority to grant final approval to damage assessments.');
        }

        $scopeCheck = $this->assertInScope($user, $assessment);
        if (!$scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($assessment->created_by === $user->id || ($assessment->submitted_by && $assessment->submitted_by === $user->id)) {
            return Response::deny('4-Eye Separation of Duties: Field assessor/submitter cannot approve their own assessment.');
        }

        return Response::allow();
    }

    public function manageCategory(User $user): Response
    {
        return $this->hasAnyRole($user, self::CATEGORY_MANAGE_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to manage damage categories.');
    }

    public function exportAssessments(User $user): Response
    {
        return $this->hasAnyRole($user, self::EXPORT_ROLES)
            ? Response::allow()
            : Response::deny('You do not have permission to export damage assessment reports.');
    }

    public function downloadAttachment(User $user, DamageAssessment $assessment): Response
    {
        if ($this->isExternalPartnerOnly($user)) {
            return Response::deny('External observers cannot download private damage assessment attachments.');
        }

        return $this->viewAssessment($user, $assessment);
    }

    public function isExternalPartnerOnly(User $user): bool
    {
        return $user->hasRole('ROLE_EXTERNAL_PARTNER')
            && !$this->hasAnyRole($user, [
                'ROLE_SUPER_ADMIN',
                'ROLE_MINISTRY_EXECUTIVE',
                'ROLE_NEOC_DIRECTOR',
                'ROLE_NATIONAL_AUDITOR',
                'ROLE_DDM_DIRECTOR_GENERAL',
                'ROLE_DDM_PLANNING_DIRECTOR',
                'ROLE_DRRO',
                'ROLE_UNO',
                'ROLE_PIO',
            ]);
    }

    protected function assertInScope(User $user, DamageAssessment $assessment): Response
    {
        if ($user->isNationalScope()) {
            return Response::allow();
        }

        $assessment->loadMissing('administrativeUnit');
        $targetPath = $assessment->administrativeUnit?->ancestry_path ?? '';

        if (!$targetPath || !$this->scopeService->canAccessScope($user, $targetPath)) {
            return Response::deny('Jurisdiction violation: This damage assessment lies outside your administrative hierarchy.');
        }

        return Response::allow();
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
