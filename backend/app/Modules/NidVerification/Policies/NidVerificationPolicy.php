<?php

namespace App\Modules\NidVerification\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\NidVerification\Models\NidVerification;
use Illuminate\Auth\Access\Response;

/**
 * Class NidVerificationPolicy
 *
 * Enforces RBAC permissions, 7-tier hierarchical scoping, and 4-eye Separation of Duties
 * for NID verification viewing, initiation, and manual review overrides.
 */
class NidVerificationPolicy
{
    protected const VIEW_ROLES = [
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
        'ROLE_TAG_OFFICER',
    ];

    protected const INITIATE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_DRRO',
        'ROLE_PIO',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
    ];

    protected const MANUAL_REVIEW_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    protected function hasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can view NID verification dashboard and history.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user, self::VIEW_ROLES)
            || $user->hasPermission('nid.verification.view_status');
    }

    /**
     * Determine whether the user can view a specific NID verification record.
     */
    public function view(User $user, NidVerification $verification): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->isNationalScope()) {
            return true;
        }

        $ancestryPath = $verification->administrativeUnit?->ancestry_path
            ?? $verification->household?->administrativeUnit?->ancestry_path;

        if (!$ancestryPath) {
            return $verification->requested_by_user_id !== null
                && $verification->requested_by_user_id === $user->id;
        }

        return $this->scopeService->canAccessScope($user, $ancestryPath);
    }

    /**
     * Determine whether the user can initiate an NID verification request.
     */
    public function initiate(User $user): bool
    {
        return $this->hasAnyRole($user, self::INITIATE_ROLES)
            || $user->hasPermission('nid.verification.initiate');
    }

    /**
     * Determine whether the user can retry a failed/pending-review NID verification.
     */
    public function retry(User $user, NidVerification $verification): bool
    {
        return $this->initiate($user) && $this->view($user, $verification);
    }

    /**
     * Determine whether the user can perform a manual review override.
     * Enforces role authorization, jurisdictional scope, and 4-eye Separation of Duties.
     */
    public function manualReview(User $user, NidVerification $verification): Response
    {
        if (!$this->view($user, $verification)) {
            return Response::deny('This NID verification record is outside your administrative jurisdiction.');
        }

        $hasReviewRole = $this->hasAnyRole($user, self::MANUAL_REVIEW_ROLES)
            || $user->hasPermission('nid.verification.manual_review');

        if (!$hasReviewRole) {
            return Response::deny('Your assigned role does not have permission to perform manual NID review overrides.');
        }

        // 4-eye Separation of Duties: initiator cannot override their own verification request
        if ($verification->requested_by_user_id && $verification->requested_by_user_id === $user->id) {
            return Response::deny('Separation of Duties (4-Eye Rule): the officer who initiated this verification cannot manually override it. Another authorized officer must review it.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can inspect technical provider adapter configuration/health.
     */
    public function viewProviderHealth(User $user): bool
    {
        return $this->viewAny($user) || $user->hasPermission('nid.adapter.configure');
    }
}
