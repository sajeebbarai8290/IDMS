<?php

namespace App\Modules\Document\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Document\Models\Document;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC (ROLE_PERMISSION_MATRIX.md Module 14), 7-tier jurisdiction scoping,
 * confidentiality restrictions, 4-eye SoD for document approvals, and ROLE_SUPER_ADMIN blackout.
 */
class DocumentPolicy
{
    public const VIEW_ROLES = [
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
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_DATA_ENTRY_OPERATOR',
        'ROLE_TAG_OFFICER',
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
        'ROLE_EXTERNAL_PARTNER',
    ];

    public const UPLOAD_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
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
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_DATA_ENTRY_OPERATOR',
        'ROLE_TAG_OFFICER',
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
        'ROLE_VOLUNTEER_RESPONDER',
    ];

    public const APPROVE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DDM_PLANNING_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    public const DELETE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_DRRO',
        'ROLE_PIO',
    ];

    public const VERIFY_ROLES = [
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
        'ROLE_TAG_OFFICER',
        'ROLE_WAREHOUSE_OFFICER',
    ];

    public const CATEGORY_MANAGE_ROLES = [
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
        if ($user->hasPermission('document.file.view') || $this->hasAnyRole($user, self::VIEW_ROLES)) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view documents.');
    }

    public function view(User $user, Document $document): Response
    {
        if (!$this->viewAny($user)->allowed()) {
            return Response::deny('You do not have permission to view documents.');
        }

        // External partners may strictly view PUBLIC, APPROVED, non-confidential documents only.
        if ($user->hasRole('ROLE_EXTERNAL_PARTNER') && !$user->isNationalScope()) {
            if (
                $document->isConfidentialFile()
                || $document->classification !== Document::CLASS_PUBLIC
                || $document->approval_status !== Document::STATUS_APPROVED
            ) {
                return Response::deny('External partners are restricted to approved public documents.');
            }

            return Response::allow();
        }

        if (!$this->isWithinJurisdiction($user, $document)) {
            return Response::deny('Document lies outside your administrative jurisdiction.');
        }

        // Restricted documents require supervisor/national/auditor tier or direct ownership
        if ($document->classification === Document::CLASS_RESTRICTED) {
            $isOwnerOrUploader = $document->uploaded_by === $user->id || $document->owner_user_id === $user->id;
            $hasPrivilegedRole = $user->isNationalScope() || $this->hasAnyRole($user, [
                'ROLE_DIVISIONAL_COMMISSIONER',
                'ROLE_DEPUTY_COMMISSIONER',
                'ROLE_DRRO',
                'ROLE_UNO',
                'ROLE_PIO',
                'ROLE_NATIONAL_AUDITOR',
            ]);

            if (!$isOwnerOrUploader && !$hasPrivilegedRole) {
                return Response::deny('Restricted classification requires authorized officer clearance.');
            }
        }

        return Response::allow();
    }

    public function upload(User $user): Response
    {
        if ($user->hasRole('ROLE_EXTERNAL_PARTNER') || $user->hasRole('ROLE_NATIONAL_AUDITOR')) {
            return Response::deny('Read-only roles cannot upload documents.');
        }

        if ($user->hasPermission('document.file.upload') || $this->hasAnyRole($user, self::UPLOAD_ROLES)) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to upload documents.');
    }

    public function update(User $user, Document $document): Response
    {
        if (!$this->upload($user)->allowed()) {
            return Response::deny('You do not have permission to modify documents.');
        }

        if (!$this->isWithinJurisdiction($user, $document)) {
            return Response::deny('Document lies outside your administrative jurisdiction.');
        }

        if ($document->approval_status === Document::STATUS_ARCHIVED && !$user->isNationalScope()) {
            return Response::deny('Archived documents cannot be modified at field or local tiers.');
        }

        return Response::allow();
    }

    public function uploadVersion(User $user, Document $document): Response
    {
        return $this->update($user, $document);
    }

    public function approve(User $user, Document $document): Response
    {
        // Operational blackout: Super Admin cannot approve operational documents
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Operational blackout: ROLE_SUPER_ADMIN cannot approve or reject documents.');
        }

        if (!$user->hasPermission('document.file.approve') && !$this->hasAnyRole($user, self::APPROVE_ROLES)) {
            return Response::deny('You do not have permission to approve documents.');
        }

        if (!$this->isWithinJurisdiction($user, $document)) {
            return Response::deny('Document lies outside your administrative jurisdiction.');
        }

        // 4-eye Separation of Duties
        if ($document->uploaded_by === $user->id || ($document->submitted_by && $document->submitted_by === $user->id)) {
            return Response::deny('Four-eye Separation of Duties: Uploader or submitter cannot approve their own document.');
        }

        return Response::allow();
    }

    public function delete(User $user, Document $document): Response
    {
        if (!$user->hasPermission('document.file.delete') && !$this->hasAnyRole($user, self::DELETE_ROLES)) {
            return Response::deny('You do not have permission to delete documents.');
        }

        if (!$this->isWithinJurisdiction($user, $document)) {
            return Response::deny('Document lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function verifyIntegrity(User $user, Document $document): Response
    {
        if (!$user->hasPermission('document.file.verify_integrity') && !$this->hasAnyRole($user, self::VERIFY_ROLES)) {
            return Response::deny('You do not have permission to verify document integrity.');
        }

        if (!$this->isWithinJurisdiction($user, $document)) {
            return Response::deny('Document lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function manageCategories(User $user): Response
    {
        if ($user->hasPermission('document.category.manage') || $this->hasAnyRole($user, self::CATEGORY_MANAGE_ROLES)) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to manage document categories.');
    }

    protected function isWithinJurisdiction(User $user, Document $document): bool
    {
        if ($user->isNationalScope()) {
            return true;
        }

        if ($document->uploaded_by === $user->id || $document->owner_user_id === $user->id) {
            return true;
        }

        $unit = $document->administrativeUnit;
        if ($unit) {
            return $this->scopeService->canAccessScope($user, $unit->ancestry_path);
        }

        return false;
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
