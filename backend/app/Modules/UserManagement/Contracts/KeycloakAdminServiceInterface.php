<?php

namespace App\Modules\UserManagement\Contracts;

use App\Core\Models\User;

interface KeycloakAdminServiceInterface
{
    /**
     * Provision or link a user identity in Keycloak without storing passwords locally.
     *
     * @param User $user
     * @param list<string> $roleCodes
     * @param array|null $scopeProfile
     * @return array
     */
    public function provisionUser(User $user, array $roleCodes, ?array $scopeProfile = null): array;

    /**
     * Synchronize profile attributes, roles, enabled state, and MFA actions with Keycloak.
     *
     * @param User $user
     * @param list<string> $roleCodes
     * @param array|null $scopeProfile
     * @return array
     */
    public function syncUser(User $user, array $roleCodes, ?array $scopeProfile = null): array;

    /**
     * Enable or disable a user's Keycloak account (for SUSPEND / DEACTIVATE / LOCK / ACTIVATE).
     */
    public function setUserEnabled(User $user, bool $enabled, ?string $reason = null): array;

    /**
     * Synchronize realm role assignments in Keycloak.
     *
     * @param User $user
     * @param list<string> $roleCodes
     * @return array
     */
    public function assignRoles(User $user, array $roleCodes): array;

    /**
     * Enforce or relax mandatory MFA (CONFIGURE_TOTP required action) in Keycloak.
     */
    public function enforceMfa(User $user, bool $enforced): array;

    /**
     * Check connectivity and configuration status of Keycloak Admin integration.
     */
    public function healthCheck(): array;
}
