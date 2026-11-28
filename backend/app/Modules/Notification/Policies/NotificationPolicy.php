<?php

namespace App\Modules\Notification\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Models\AlertRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDispatch;
use Illuminate\Auth\Access\Response;

/**
 * Enforces RBAC (ROLE_PERMISSION_MATRIX.md Module 13), 7-tier jurisdiction scoping,
 * and ROLE_SUPER_ADMIN operational blackout on emergency broadcasts & alert acknowledgements.
 */
class NotificationPolicy
{
    public const LOG_VIEW_ROLES = [
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
    ];

    public const TEMPLATE_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
    ];

    public const BROADCAST_SEND_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_UNO',
    ];

    public const ALERT_RULE_MANAGE_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
    ];

    public const ALERT_ACK_ROLES = [
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
        'ROLE_WAREHOUSE_OFFICER',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_LEADER',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function viewLogs(User $user): Response
    {
        if ($user->hasPermission('notification.log.view') || $this->hasAnyRole($user, self::LOG_VIEW_ROLES)) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view notification logs.');
    }

    public function viewNotification(User $user, Notification $notification): Response
    {
        if (!$this->viewLogs($user)->allowed()) {
            return Response::deny('You do not have permission to view notifications.');
        }

        if ($user->isNationalScope()) {
            return Response::allow();
        }

        if ($notification->created_by === $user->id) {
            return Response::allow();
        }

        $unit = $notification->administrativeUnit;
        if ($unit && $this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
            return Response::allow();
        }

        return Response::deny('Notification lies outside your administrative jurisdiction.');
    }

    public function viewDispatch(User $user, NotificationDispatch $dispatch): Response
    {
        if ($dispatch->recipient_user_id === $user->id) {
            return Response::allow();
        }

        if (!$this->viewLogs($user)->allowed()) {
            return Response::deny('You do not have permission to view notification dispatches.');
        }

        if ($user->isNationalScope()) {
            return Response::allow();
        }

        $unit = $dispatch->administrativeUnit;
        if ($unit && $this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
            return Response::allow();
        }

        return Response::deny('Notification dispatch lies outside your administrative jurisdiction.');
    }

    public function manageTemplates(User $user): Response
    {
        if ($this->hasAnyRole($user, self::TEMPLATE_MANAGE_ROLES) || $user->hasPermission('notification.template.manage')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to manage notification templates.');
    }

    public function sendBroadcast(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is blocked from sending operational emergency broadcasts.');
        }

        if ($user->hasRole('ROLE_NATIONAL_AUDITOR') || $user->hasRole('ROLE_EXTERNAL_PARTNER')) {
            return Response::deny('Read-only roles cannot dispatch notifications or broadcasts.');
        }

        if ($this->hasAnyRole($user, self::BROADCAST_SEND_ROLES) || $user->hasPermission('notification.broadcast.send')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to send notifications or broadcasts.');
    }

    public function manageAlertRules(User $user): Response
    {
        if ($this->hasAnyRole($user, self::ALERT_RULE_MANAGE_ROLES) || $user->hasPermission('notification.alert.manage')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to manage alert rules.');
    }

    public function viewAlert(User $user, Alert $alert): Response
    {
        if (!$this->viewLogs($user)->allowed() && !$this->hasAnyRole($user, self::ALERT_ACK_ROLES)) {
            return Response::deny('You do not have permission to view alerts.');
        }

        if ($user->isNationalScope()) {
            return Response::allow();
        }

        $unit = $alert->administrativeUnit;
        if ($unit && $this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
            return Response::allow();
        }

        return Response::deny('Alert lies outside your administrative jurisdiction.');
    }

    public function acknowledgeAlert(User $user, Alert $alert): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is blocked from acknowledging or resolving operational alerts.');
        }

        if ($user->hasRole('ROLE_NATIONAL_AUDITOR') || $user->hasRole('ROLE_EXTERNAL_PARTNER')) {
            return Response::deny('Read-only roles cannot acknowledge or resolve alerts.');
        }

        if (!$this->hasAnyRole($user, self::ALERT_ACK_ROLES) && !$user->hasPermission('notification.alert.acknowledge')) {
            return Response::deny('You do not have permission to acknowledge or resolve alerts.');
        }

        return $this->viewAlert($user, $alert);
    }

    public function triggerAlert(User $user): Response
    {
        if ($user->hasRole('ROLE_SUPER_ADMIN')) {
            return Response::deny('Super Admin is blocked from triggering operational alerts.');
        }

        if ($user->hasRole('ROLE_NATIONAL_AUDITOR') || $user->hasRole('ROLE_EXTERNAL_PARTNER')) {
            return Response::deny('Read-only roles cannot trigger alerts.');
        }

        if (
            $this->hasAnyRole($user, self::BROADCAST_SEND_ROLES)
            || $this->hasAnyRole($user, self::ALERT_RULE_MANAGE_ROLES)
            || $user->hasPermission('notification.alert.manage')
            || $user->hasPermission('notification.broadcast.send')
        ) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to trigger alerts.');
    }

    public function viewRule(User $user, AlertRule $rule): Response
    {
        if (!$this->viewLogs($user)->allowed() && !$this->manageAlertRules($user)->allowed()) {
            return Response::deny('You do not have permission to view alert rules.');
        }

        if ($user->isNationalScope() || !$rule->administrativeUnit) {
            return Response::allow();
        }

        return $this->scopeService->canAccessScope($user, $rule->administrativeUnit->ancestry_path)
            ? Response::allow()
            : Response::deny('Alert rule lies outside your jurisdiction.');
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
