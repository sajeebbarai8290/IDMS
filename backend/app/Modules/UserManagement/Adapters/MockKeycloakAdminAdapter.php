<?php

namespace App\Modules\UserManagement\Adapters;

use App\Core\Models\User;
use App\Modules\UserManagement\Contracts\KeycloakAdminServiceInterface;
use Illuminate\Support\Str;

/**
 * Development & Automated Testing Adapter for Keycloak Admin Operations.
 * Never used in production (enforced in AppServiceProvider).
 */
class MockKeycloakAdminAdapter implements KeycloakAdminServiceInterface
{
    public function provisionUser(User $user, array $roleCodes, ?array $scopeProfile = null): array
    {
        $sub = $user->keycloak_sub ?: (string) Str::uuid();
        $username = $user->keycloak_username ?: $this->deriveUsername($user);

        return [
            'success' => true,
            'is_mock' => true,
            'keycloak_sub' => $sub,
            'keycloak_username' => $username,
            'enabled' => $user->isOperationalActive(),
            'mfa_configured' => (bool) $user->mfa_verified,
            'mfa_enforced' => (bool) $user->mfa_enforced,
            'idp_sync_status' => User::IDP_SYNC_LINKED,
            'roles' => array_values($roleCodes),
            'error_code' => null,
            'error_message' => null,
        ];
    }

    public function syncUser(User $user, array $roleCodes, ?array $scopeProfile = null): array
    {
        return $this->provisionUser($user, $roleCodes, $scopeProfile);
    }

    public function setUserEnabled(User $user, bool $enabled, ?string $reason = null): array
    {
        return [
            'success' => true,
            'is_mock' => true,
            'keycloak_sub' => $user->keycloak_sub ?: (string) Str::uuid(),
            'enabled' => $enabled,
            'idp_sync_status' => $enabled ? User::IDP_SYNC_LINKED : User::IDP_SYNC_LOCKED,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    public function assignRoles(User $user, array $roleCodes): array
    {
        return [
            'success' => true,
            'is_mock' => true,
            'keycloak_sub' => $user->keycloak_sub,
            'roles' => array_values($roleCodes),
            'idp_sync_status' => $user->keycloak_sub ? User::IDP_SYNC_LINKED : User::IDP_SYNC_PENDING,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    public function enforceMfa(User $user, bool $enforced): array
    {
        return [
            'success' => true,
            'is_mock' => true,
            'keycloak_sub' => $user->keycloak_sub,
            'mfa_enforced' => $enforced,
            'required_actions' => $enforced ? ['CONFIGURE_TOTP'] : [],
            'idp_sync_status' => $user->keycloak_sub ? User::IDP_SYNC_LINKED : User::IDP_SYNC_PENDING,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    public function healthCheck(): array
    {
        return [
            'driver' => 'mock',
            'is_mock' => true,
            'configured' => true,
            'reachable' => true,
            'realm' => config('services.keycloak.realm', 'idms-gov-realm'),
            'status' => 'MOCK_READY',
            'message' => 'Using local mock Keycloak Admin adapter (development/testing only).',
        ];
    }

    protected function deriveUsername(User $user): string
    {
        $localPart = explode('@', (string) $user->email)[0] ?: 'officer';
        return Str::slug($localPart, '_');
    }
}
