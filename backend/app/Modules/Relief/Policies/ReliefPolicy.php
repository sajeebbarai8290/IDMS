<?php

namespace App\Modules\Relief\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefRequisition;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC, 7-tier administrative jurisdiction, 4-eye Separation of Duties (SoD),
 * and ROLE_SUPER_ADMIN operational blackout for Module 04 (Relief Distribution Management).
 */
class ReliefPolicy
{
    protected const CATEGORY_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
    ];

    protected const REQUISITION_VIEW_ROLES = [
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

    protected const REQUISITION_CREATE_ROLES = [
        'ROLE_DRRO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_SHELTER_MANAGER',
    ];

    protected const REQUISITION_DELETE_ROLES = [
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
    ];

    protected const REQUISITION_APPROVE_ROLES = [
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

    protected const ALLOCATION_VIEW_ROLES = [
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

    protected const ALLOCATION_CREATE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    protected const ALLOCATION_APPROVE_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
    ];

    protected const DISTRIBUTION_RECORD_ROLES = [
        'ROLE_DRRO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_SHELTER_MANAGER',
        'ROLE_VOLUNTEER',
    ];

    protected const DISTRIBUTION_VERIFY_ROLES = [
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_TAG_OFFICER',
    ];

    protected const DISTRIBUTION_VOID_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function manageCategories(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.category.manage',
            self::CATEGORY_MANAGE_ROLES,
            'Insufficient permission to manage relief categories.'
        );
    }

    public function viewAnyRequisitions(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.requisition.view',
            self::REQUISITION_VIEW_ROLES,
            'You are not authorized to view relief requisitions.'
        );
    }

    public function viewRequisition(User $user, ReliefRequisition $requisition): Response
    {
        $roleCheck = $this->viewAnyRequisitions($user);
        if (!$roleCheck->allowed()) {
            return $roleCheck;
        }

        return $this->checkScope($user, $requisition->administrativeUnit?->ancestry_path ?? '/');
    }

    public function createRequisition(User $user): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'create or submit relief requisitions')) {
            return $blackout;
        }

        return $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.requisition.create',
            self::REQUISITION_CREATE_ROLES,
            'Your role is not authorized to create relief requisitions.'
        );
    }

    public function updateRequisition(User $user, ReliefRequisition $requisition): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'modify relief requisitions')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.requisition.update',
            self::REQUISITION_CREATE_ROLES,
            'Your role is not authorized to update relief requisitions.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        return $this->checkScope($user, $requisition->administrativeUnit?->ancestry_path ?? '/');
    }

    public function deleteRequisition(User $user, ReliefRequisition $requisition): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'delete relief requisitions')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.requisition.delete',
            self::REQUISITION_DELETE_ROLES,
            'Your role is not authorized to delete relief requisitions.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        return $this->checkScope($user, $requisition->administrativeUnit?->ancestry_path ?? '/');
    }

    public function approveRequisition(User $user, ReliefRequisition $requisition): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'approve or reject relief requisitions')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.requisition.approve',
            self::REQUISITION_APPROVE_ROLES,
            'Your role is not authorized to review or approve relief requisitions.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        $scopeCheck = $this->checkScope($user, $requisition->administrativeUnit?->ancestry_path ?? '/');
        if (!$scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($requisition->created_by === $user->id || $requisition->submitted_by === $user->id) {
            return Response::deny('Four-eyes separation of duties: you cannot approve or reject your own requisition.');
        }

        return Response::allow();
    }

    public function viewAnyAllocations(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.allocation.view',
            self::ALLOCATION_VIEW_ROLES,
            'You are not authorized to view relief allocations.'
        );
    }

    public function viewAllocation(User $user, ReliefAllocation $allocation): Response
    {
        $roleCheck = $this->viewAnyAllocations($user);
        if (!$roleCheck->allowed()) {
            return $roleCheck;
        }

        return $this->checkScope($user, $allocation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function createAllocation(User $user): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'create relief allocations')) {
            return $blackout;
        }

        return $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.allocation.create',
            self::ALLOCATION_CREATE_ROLES,
            'Your role is not authorized to create relief allocations.'
        );
    }

    public function updateAllocation(User $user, ReliefAllocation $allocation): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'update relief allocations')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.allocation.create',
            self::ALLOCATION_CREATE_ROLES,
            'Your role is not authorized to modify relief allocations.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        return $this->checkScope($user, $allocation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function approveAllocation(User $user, ReliefAllocation $allocation): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'sanction or approve relief allocations')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.allocation.approve',
            self::ALLOCATION_APPROVE_ROLES,
            'Your role is not authorized to sanction relief allocations.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        $scopeCheck = $this->checkScope($user, $allocation->administrativeUnit?->ancestry_path ?? '/');
        if (!$scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($allocation->created_by === $user->id) {
            return Response::deny('Four-eyes separation of duties: you cannot sanction your own allocation order.');
        }

        return Response::allow();
    }

    public function dispatchAllocation(User $user, ReliefAllocation $allocation): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'dispatch relief allocations')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.allocation.create',
            array_merge(self::ALLOCATION_CREATE_ROLES, ['ROLE_WAREHOUSE_MANAGER']),
            'Your role is not authorized to dispatch relief allocations.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        return $this->checkScope($user, $allocation->administrativeUnit?->ancestry_path ?? '/');
    }

    public function viewAnyDistributions(User $user): Response
    {
        return $this->viewAnyAllocations($user);
    }

    public function viewDistribution(User $user, DistributionVoucher $voucher): Response
    {
        $roleCheck = $this->viewAnyDistributions($user);
        if (!$roleCheck->allowed()) {
            return $roleCheck;
        }

        $voucher->loadMissing('distributionPoint.administrativeUnit');

        return $this->checkScope($user, $voucher->distributionPoint?->administrativeUnit?->ancestry_path ?? '/');
    }

    public function recordDistribution(User $user, ?ReliefAllocation $allocation = null): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'record physical relief distributions')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.distribution.record',
            self::DISTRIBUTION_RECORD_ROLES,
            'Your role is not authorized to record beneficiary relief distributions.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        if ($allocation !== null) {
            return $this->checkScope($user, $allocation->administrativeUnit?->ancestry_path ?? '/');
        }

        return Response::allow();
    }

    public function verifyDistribution(User $user, DistributionVoucher $voucher): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'verify distribution vouchers')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.distribution.verify',
            self::DISTRIBUTION_VERIFY_ROLES,
            'Your role is not authorized to witness-verify distribution vouchers.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        $voucher->loadMissing('distributionPoint.administrativeUnit');
        $scopeCheck = $this->checkScope($user, $voucher->distributionPoint?->administrativeUnit?->ancestry_path ?? '/');
        if (!$scopeCheck->allowed()) {
            return $scopeCheck;
        }

        if ($voucher->distributed_by === $user->id) {
            return Response::deny('Four-eyes separation of duties: you cannot witness-verify your own distribution voucher.');
        }

        return Response::allow();
    }

    public function voidDistribution(User $user, DistributionVoucher $voucher): Response
    {
        if ($blackout = $this->denySuperAdmin($user, 'void distribution vouchers')) {
            return $blackout;
        }

        $permCheck = $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.allocation.approve',
            self::DISTRIBUTION_VOID_ROLES,
            'Your role is not authorized to void distribution vouchers.'
        );
        if (!$permCheck->allowed()) {
            return $permCheck;
        }

        $voucher->loadMissing('distributionPoint.administrativeUnit');
        return $this->checkScope($user, $voucher->distributionPoint?->administrativeUnit?->ancestry_path ?? '/');
    }

    public function exportMuster(User $user): Response
    {
        return $this->allowIfHasPermissionOrRoles(
            $user,
            'relief.distribution.export_muster',
            array_diff(self::ALLOCATION_VIEW_ROLES, ['ROLE_EXTERNAL_PARTNER']),
            'Your role is not authorized to export relief muster rolls.'
        );
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

        return Response::deny('Jurisdiction access denied: record falls outside your administrative hierarchy.');
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
