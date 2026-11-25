<?php

namespace App\Modules\Disaster\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Auth\Access\Response;

/**
 * Class DisasterPolicy
 *
 * Enforces RBAC and 7-tier hierarchical scoping for Disaster Information Management (Module 03).
 */
class DisasterPolicy
{
    protected const CREATE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    protected const UPDATE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    protected const VERIFY_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
    ];

    protected const WARNING_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
    ];

    protected const CATEGORY_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
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

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DisasterEvent $event): bool
    {
        if ($user->isNationalScope() || $event->is_national || $event->created_by === $user->id) {
            return true;
        }

        $officerPath = $user->getAdministrativeScope()['ancestry_path'] ?? '/';
        if ($officerPath === '/' || $officerPath === '') {
            return true;
        }

        $normOfficer = rtrim($officerPath, '/') . '/';

        foreach ($event->locations()->with('administrativeUnit')->get() as $location) {
            $unitPath = $location->administrativeUnit?->ancestry_path;
            if (!$unitPath) {
                continue;
            }
            $normUnit = rtrim($unitPath, '/') . '/';

            if (str_starts_with($normUnit, $normOfficer) || str_starts_with($normOfficer, $normUnit)) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user): Response
    {
        if ($this->hasAnyRole($user, self::CREATE_ROLES) || $user->hasPermission('disaster.event.create')) {
            return Response::allow();
        }

        return Response::deny('Your role does not have permission to register disaster events.');
    }

    public function update(User $user, DisasterEvent $event): Response
    {
        if (!$this->view($user, $event)) {
            return Response::deny('This disaster event is outside your administrative jurisdiction.');
        }

        if ($this->hasAnyRole($user, self::UPDATE_ROLES) || $user->hasPermission('disaster.event.update')) {
            return Response::allow();
        }

        return Response::deny('Your role does not have permission to update disaster events.');
    }

    public function verify(User $user, DisasterEvent $event): Response
    {
        if (!$this->view($user, $event)) {
            return Response::deny('This disaster event is outside your administrative jurisdiction.');
        }

        if ($this->hasAnyRole($user, self::VERIFY_ROLES) || $user->hasPermission('disaster.event.verify')) {
            return Response::allow();
        }

        return Response::deny('Only District/Division/Directorate/Ministry authorities can verify, activate, or close disasters.');
    }

    public function broadcastWarning(User $user, DisasterEvent $event): Response
    {
        if (!$this->view($user, $event)) {
            return Response::deny('This disaster event is outside your administrative jurisdiction.');
        }

        if ($this->hasAnyRole($user, self::WARNING_ROLES) || $user->hasPermission('disaster.warning.broadcast')) {
            return Response::allow();
        }

        return Response::deny('Your role does not have permission to issue early warnings.');
    }

    public function manageCategories(User $user): Response
    {
        if ($this->hasAnyRole($user, self::CATEGORY_ROLES) || $user->hasPermission('disaster.category.manage')) {
            return Response::allow();
        }

        return Response::deny('Only National or DDM authorities can manage disaster categories.');
    }

    public function delete(User $user, DisasterEvent $event): Response
    {
        if (!$this->view($user, $event)) {
            return Response::deny('This disaster event is outside your administrative jurisdiction.');
        }

        if ($this->hasAnyRole($user, self::VERIFY_ROLES) || $user->hasPermission('disaster.event.delete')) {
            return Response::allow();
        }

        return Response::deny('Your role does not have permission to archive/delete disaster events.');
    }
}
