<?php

namespace App\Modules\UserManagement\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC (ROLE_PERMISSION_MATRIX.md Module 12), 7-tier hierarchical scoping,
 * and privilege-escalation prevention across User, Role, Permission, and Organization management.
 */
class UserManagementPolicy
{
    public const VIEW_USER_ROLES = [
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
    ];

    public const ADMIN_ROLES = [
        'ROLE_SUPER_ADMIN',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function viewAny(User $actor): Response
    {
        if ($actor->hasPermission('user.account.view') || $this->hasAnyRole($actor, self::VIEW_USER_ROLES)) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view user accounts.');
    }

    public function viewUser(User $actor, User $target): Response
    {
        if ($actor->id === $target->id) {
            return Response::allow();
        }

        if (!$this->viewAny($actor)->allowed()) {
            return Response::deny('You do not have permission to view user accounts.');
        }

        if ($this->isTargetInActorJurisdiction($actor, $target)) {
            return Response::allow();
        }

        return Response::deny('Target user lies outside your administrative jurisdiction.');
    }

    public function createUser(User $actor): Response
    {
        if ($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('user.account.create')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to provision user accounts.');
    }

    public function updateUser(User $actor, User $target): Response
    {
        if (!($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('user.account.update'))) {
            return Response::deny('You do not have permission to update user accounts.');
        }

        if ($target->hasRole('ROLE_SUPER_ADMIN') && !$actor->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Only a Super Administrator may modify a Super Administrator account.');
        }

        if (!$this->isTargetInActorJurisdiction($actor, $target)) {
            return Response::deny('Target user lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function deleteUser(User $actor, User $target): Response
    {
        if ($actor->id === $target->id) {
            return Response::deny('You cannot delete or deactivate your own active account.');
        }

        if (!($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('user.account.delete'))) {
            return Response::deny('You do not have permission to archive user accounts.');
        }

        if ($target->hasRole('ROLE_SUPER_ADMIN') && !$actor->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Only a Super Administrator may archive a Super Administrator account.');
        }

        if (!$this->isTargetInActorJurisdiction($actor, $target)) {
            return Response::deny('Target user lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function changeStatus(User $actor, User $target): Response
    {
        if ($actor->id === $target->id) {
            return Response::deny('You cannot suspend, deactivate, or lock your own active account.');
        }

        if (!($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('user.account.deactivate'))) {
            return Response::deny('You do not have permission to change user account lifecycle status.');
        }

        if ($target->hasRole('ROLE_SUPER_ADMIN') && !$actor->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Only a Super Administrator may change the status of a Super Administrator.');
        }

        if (!$this->isTargetInActorJurisdiction($actor, $target)) {
            return Response::deny('Target user lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function assignRoles(User $actor, User $target): Response
    {
        if (!($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('role.assignment.manage'))) {
            return Response::deny('You do not have permission to manage role assignments.');
        }

        if ($target->hasRole('ROLE_SUPER_ADMIN') && !$actor->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Only a Super Administrator may alter Super Administrator role assignments.');
        }

        if (!$this->isTargetInActorJurisdiction($actor, $target)) {
            return Response::deny('Target user lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function assignScope(User $actor, User $target): Response
    {
        if (!($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('user.scope.assign'))) {
            return Response::deny('You do not have permission to assign organizational or geographical scopes.');
        }

        if (!$this->isTargetInActorJurisdiction($actor, $target)) {
            return Response::deny('Target user lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function syncKeycloak(User $actor, User $target): Response
    {
        if (!($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('user.keycloak.sync'))) {
            return Response::deny('You do not have permission to synchronize Keycloak identities.');
        }

        if (!$this->isTargetInActorJurisdiction($actor, $target)) {
            return Response::deny('Target user lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function viewActivity(User $actor, ?User $target = null): Response
    {
        if ($target && $actor->id === $target->id) {
            return Response::allow();
        }

        $allowed = $this->hasAnyRole($actor, [
            'ROLE_SUPER_ADMIN',
            'ROLE_MINISTRY_EXECUTIVE',
            'ROLE_NATIONAL_AUDITOR',
            'ROLE_DDM_DIRECTOR_GENERAL',
            'ROLE_DEPUTY_COMMISSIONER',
            'ROLE_DRRO',
            'ROLE_UNO',
            'ROLE_PIO',
        ]) || $actor->hasPermission('user.activity.view') || $actor->hasPermission('audit.log.view');

        if (!$allowed) {
            return Response::deny('You do not have permission to view user activity logs.');
        }

        if ($target && !$this->isTargetInActorJurisdiction($actor, $target)) {
            return Response::deny('Target user activity lies outside your administrative jurisdiction.');
        }

        return Response::allow();
    }

    public function viewRoles(User $actor): Response
    {
        if ($actor->hasPermission('role.permission.view') || $actor->isOperationalActive()) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view roles and permissions.');
    }

    public function manageRoleConfiguration(User $actor, ?Role $role = null): Response
    {
        if (!($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('role.configuration.manage'))) {
            return Response::deny('Only authorized security administrators may configure roles and permissions.');
        }

        if ($role && $role->code === 'ROLE_SUPER_ADMIN' && !$actor->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Only a Super Administrator may modify the ROLE_SUPER_ADMIN permission set.');
        }

        return Response::allow();
    }

    public function viewOrganizations(User $actor): Response
    {
        if ($actor->hasPermission('organization.view') || $this->viewAny($actor)->allowed()) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view organizations.');
    }

    public function viewOrganization(User $actor, Organization $organization): Response
    {
        if (!$this->viewOrganizations($actor)->allowed()) {
            return Response::deny('You do not have permission to view organizations.');
        }

        if ($actor->isNationalScope()) {
            return Response::allow();
        }

        if ($organization->administrativeUnit && $this->scopeService->canAccessScope($actor, $organization->administrativeUnit->ancestry_path)) {
            return Response::allow();
        }

        return Response::deny('Organization lies outside your administrative jurisdiction.');
    }

    public function manageOrganizations(User $actor, ?Organization $organization = null): Response
    {
        if (!($this->hasAnyRole($actor, self::ADMIN_ROLES) || $actor->hasPermission('organization.manage'))) {
            return Response::deny('You do not have permission to manage organizations.');
        }

        if ($organization && !$actor->isNationalScope() && $organization->administrativeUnit) {
            if (!$this->scopeService->canAccessScope($actor, $organization->administrativeUnit->ancestry_path)) {
                return Response::deny('Organization lies outside your administrative jurisdiction.');
            }
        }

        return Response::allow();
    }

    /**
     * Check whether actor's hierarchy rank permits assigning the target role.
     */
    public function getActorMinimumHierarchyRank(User $actor): int
    {
        if ($actor->hasRole('ROLE_SUPER_ADMIN')) {
            return 1;
        }

        $ranks = Role::whereIn('code', $actor->getEffectiveRoleCodes())
            ->pluck('hierarchy_rank')
            ->all();

        return !empty($ranks) ? min($ranks) : 7;
    }

    public function isTargetInActorJurisdiction(User $actor, User $target): bool
    {
        if ($actor->isNationalScope()) {
            return true;
        }

        $targetPaths = $target->getAccessibleAncestryPaths();
        foreach ($targetPaths as $path) {
            if ($path !== '/' && $this->scopeService->canAccessScope($actor, $path)) {
                return true;
            }
        }

        // If target has an explicit administrativeUnit, evaluate that directly
        if ($target->administrativeUnit) {
            return $this->scopeService->canAccessScope($actor, $target->administrativeUnit->ancestry_path);
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
