<?php

namespace App\Modules\UserManagement\Adapters;

use App\Core\Models\User;
use App\Modules\UserManagement\Contracts\KeycloakAdminServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Production Keycloak Admin REST API Adapter.
 * Uses confidential service-account credentials via config('services.keycloak.*').
 * Never stores or transmits local passwords.
 */
class KeycloakAdminHttpAdapter implements KeycloakAdminServiceInterface
{
    protected string $baseUrl;
    protected string $realm;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.keycloak.base_url', 'http://localhost:8080'), '/');
        $this->realm = (string) config('services.keycloak.realm', 'idms-gov-realm');
        $this->clientId = config('services.keycloak.admin_client_id');
        $this->clientSecret = config('services.keycloak.admin_client_secret');
        $this->timeout = max(2, (int) config('services.keycloak.admin_timeout_seconds', 5));
    }

    public function provisionUser(User $user, array $roleCodes, ?array $scopeProfile = null): array
    {
        $token = $this->acquireAdminToken();
        if (!$token['success']) {
            return $this->failureResult($token['error_code'], $token['error_message'], $user);
        }

        try {
            $accessToken = $token['access_token'];
            $existing = $this->findKeycloakUser($accessToken, $user);

            if ($existing) {
                $userId = $existing['id'];
                $updateRes = $this->updateKeycloakUserPayload($accessToken, $userId, $user, $scopeProfile);
                if (!$updateRes['success']) {
                    return $updateRes;
                }
            } else {
                $username = $user->keycloak_username ?: Str::slug(explode('@', (string) $user->email)[0] ?: 'officer', '_');
                $requiredActions = ['UPDATE_PASSWORD'];
                if ($user->mfa_enforced) {
                    $requiredActions[] = 'CONFIGURE_TOTP';
                }

                $payload = [
                    'username' => $username,
                    'email' => $user->email,
                    'firstName' => $user->full_name_en,
                    'enabled' => $user->isOperationalActive(),
                    'emailVerified' => false,
                    'requiredActions' => $requiredActions,
                    'attributes' => $this->buildAttributes($user, $scopeProfile),
                ];

                $createResponse = Http::withToken($accessToken)
                    ->timeout($this->timeout)
                    ->post("{$this->baseUrl}/admin/realms/{$this->realm}/users", $payload);

                if (!$createResponse->successful()) {
                    return $this->failureResult(
                        'KEYCLOAK_PROVISION_FAILED',
                        'Keycloak user provisioning returned HTTP ' . $createResponse->status(),
                        $user
                    );
                }

                $found = $this->findKeycloakUser($accessToken, $user);
                $userId = $found['id'] ?? $user->keycloak_sub;
                $existing = $found ?? ['username' => $username];
            }

            if ($userId && !empty($roleCodes)) {
                $roleSync = $this->syncRealmRolesById($accessToken, $userId, $roleCodes);
                if (!$roleSync['success']) {
                    return $roleSync;
                }
            }

            return [
                'success' => true,
                'is_mock' => false,
                'keycloak_sub' => $userId,
                'keycloak_username' => $existing['username'] ?? $user->keycloak_username,
                'enabled' => $user->isOperationalActive(),
                'mfa_enforced' => (bool) $user->mfa_enforced,
                'idp_sync_status' => $userId ? User::IDP_SYNC_LINKED : User::IDP_SYNC_PENDING,
                'roles' => array_values($roleCodes),
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('Keycloak Admin provision/sync exception: ' . $e->getMessage());
            return $this->failureResult('KEYCLOAK_UNREACHABLE', 'Identity provider communication error.', $user);
        }
    }

    public function syncUser(User $user, array $roleCodes, ?array $scopeProfile = null): array
    {
        return $this->provisionUser($user, $roleCodes, $scopeProfile);
    }

    public function setUserEnabled(User $user, bool $enabled, ?string $reason = null): array
    {
        $token = $this->acquireAdminToken();
        if (!$token['success']) {
            return $this->failureResult($token['error_code'], $token['error_message'], $user);
        }

        if (!$user->keycloak_sub) {
            return $this->failureResult('KEYCLOAK_SUB_MISSING', 'User is not yet linked to a Keycloak UUID.', $user);
        }

        try {
            $response = Http::withToken($token['access_token'])
                ->timeout($this->timeout)
                ->put("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$user->keycloak_sub}", [
                    'enabled' => $enabled,
                ]);

            if (!$response->successful()) {
                return $this->failureResult(
                    'KEYCLOAK_STATUS_UPDATE_FAILED',
                    'Keycloak status update returned HTTP ' . $response->status(),
                    $user
                );
            }

            if (!$enabled) {
                Http::withToken($token['access_token'])
                    ->timeout($this->timeout)
                    ->post("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$user->keycloak_sub}/logout");
            }

            return [
                'success' => true,
                'is_mock' => false,
                'keycloak_sub' => $user->keycloak_sub,
                'enabled' => $enabled,
                'idp_sync_status' => $enabled ? User::IDP_SYNC_LINKED : User::IDP_SYNC_LOCKED,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('Keycloak setUserEnabled exception: ' . $e->getMessage());
            return $this->failureResult('KEYCLOAK_UNREACHABLE', 'Identity provider communication error.', $user);
        }
    }

    public function assignRoles(User $user, array $roleCodes): array
    {
        $token = $this->acquireAdminToken();
        if (!$token['success']) {
            return $this->failureResult($token['error_code'], $token['error_message'], $user);
        }

        if (!$user->keycloak_sub) {
            return $this->failureResult('KEYCLOAK_SUB_MISSING', 'User is not yet linked to a Keycloak UUID.', $user);
        }

        try {
            return $this->syncRealmRolesById($token['access_token'], $user->keycloak_sub, $roleCodes);
        } catch (Throwable $e) {
            Log::warning('Keycloak assignRoles exception: ' . $e->getMessage());
            return $this->failureResult('KEYCLOAK_UNREACHABLE', 'Identity provider communication error.', $user);
        }
    }

    public function enforceMfa(User $user, bool $enforced): array
    {
        $token = $this->acquireAdminToken();
        if (!$token['success']) {
            return $this->failureResult($token['error_code'], $token['error_message'], $user);
        }

        if (!$user->keycloak_sub) {
            return $this->failureResult('KEYCLOAK_SUB_MISSING', 'User is not yet linked to a Keycloak UUID.', $user);
        }

        try {
            $current = Http::withToken($token['access_token'])
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$user->keycloak_sub}");

            if (!$current->successful()) {
                return $this->failureResult('KEYCLOAK_FETCH_FAILED', 'Unable to inspect Keycloak user profile.', $user);
            }

            $actions = (array) ($current->json('requiredActions') ?? []);
            $actions = array_values(array_filter($actions, fn ($a) => $a !== 'CONFIGURE_TOTP'));
            if ($enforced) {
                $actions[] = 'CONFIGURE_TOTP';
            }

            $update = Http::withToken($token['access_token'])
                ->timeout($this->timeout)
                ->put("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$user->keycloak_sub}", [
                    'requiredActions' => $actions,
                ]);

            if (!$update->successful()) {
                return $this->failureResult('KEYCLOAK_MFA_UPDATE_FAILED', 'Failed to update Keycloak MFA required action.', $user);
            }

            return [
                'success' => true,
                'is_mock' => false,
                'keycloak_sub' => $user->keycloak_sub,
                'mfa_enforced' => $enforced,
                'required_actions' => $actions,
                'idp_sync_status' => User::IDP_SYNC_LINKED,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('Keycloak enforceMfa exception: ' . $e->getMessage());
            return $this->failureResult('KEYCLOAK_UNREACHABLE', 'Identity provider communication error.', $user);
        }
    }

    public function healthCheck(): array
    {
        $configured = !empty($this->clientId) && !empty($this->clientSecret);
        if (!$configured) {
            return [
                'driver' => 'keycloak',
                'is_mock' => false,
                'configured' => false,
                'reachable' => false,
                'realm' => $this->realm,
                'status' => 'NOT_CONFIGURED',
                'message' => 'Keycloak Admin service account credentials are not configured.',
            ];
        }

        $token = $this->acquireAdminToken();

        return [
            'driver' => 'keycloak',
            'is_mock' => false,
            'configured' => true,
            'reachable' => $token['success'],
            'realm' => $this->realm,
            'status' => $token['success'] ? 'CONNECTED' : 'UNREACHABLE',
            'message' => $token['success']
                ? 'Keycloak Admin REST API connected.'
                : ($token['error_message'] ?? 'Unable to reach Keycloak Admin API.'),
        ];
    }

    protected function acquireAdminToken(): array
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return [
                'success' => false,
                'error_code' => 'KEYCLOAK_ADMIN_NOT_CONFIGURED',
                'error_message' => 'Keycloak Admin service account credentials are not configured.',
            ];
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            if (!$response->successful() || !$response->json('access_token')) {
                return [
                    'success' => false,
                    'error_code' => 'KEYCLOAK_ADMIN_AUTH_FAILED',
                    'error_message' => 'Keycloak Admin authentication failed (HTTP ' . $response->status() . ').',
                ];
            }

            return [
                'success' => true,
                'access_token' => $response->json('access_token'),
            ];
        } catch (Throwable $e) {
            Log::warning('Keycloak Admin token request failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error_code' => 'KEYCLOAK_UNREACHABLE',
                'error_message' => 'Unable to reach Keycloak identity provider.',
            ];
        }
    }

    protected function findKeycloakUser(string $accessToken, User $user): ?array
    {
        if ($user->keycloak_sub) {
            $byId = Http::withToken($accessToken)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$user->keycloak_sub}");
            if ($byId->successful() && is_array($byId->json())) {
                return $byId->json();
            }
        }

        $byEmail = Http::withToken($accessToken)
            ->timeout($this->timeout)
            ->get("{$this->baseUrl}/admin/realms/{$this->realm}/users", [
                'email' => $user->email,
                'exact' => 'true',
            ]);

        if ($byEmail->successful() && is_array($byEmail->json()) && !empty($byEmail->json()[0])) {
            return $byEmail->json()[0];
        }

        return null;
    }

    protected function updateKeycloakUserPayload(string $accessToken, string $userId, User $user, ?array $scopeProfile): array
    {
        $response = Http::withToken($accessToken)
            ->timeout($this->timeout)
            ->put("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$userId}", [
                'email' => $user->email,
                'firstName' => $user->full_name_en,
                'enabled' => $user->isOperationalActive(),
                'attributes' => $this->buildAttributes($user, $scopeProfile),
            ]);

        if (!$response->successful()) {
            return $this->failureResult('KEYCLOAK_UPDATE_FAILED', 'Keycloak user update failed.', $user);
        }

        return ['success' => true];
    }

    protected function syncRealmRolesById(string $accessToken, string $userId, array $roleCodes): array
    {
        $realmRolesResp = Http::withToken($accessToken)
            ->timeout($this->timeout)
            ->get("{$this->baseUrl}/admin/realms/{$this->realm}/roles");

        if (!$realmRolesResp->successful()) {
            return $this->failureResult('KEYCLOAK_ROLES_FETCH_FAILED', 'Unable to list Keycloak realm roles.');
        }

        $availableRoles = collect($realmRolesResp->json() ?? [])->keyBy('name');
        $mappedRoles = [];
        foreach ($roleCodes as $code) {
            if ($availableRoles->has($code)) {
                $r = $availableRoles->get($code);
                $mappedRoles[] = ['id' => $r['id'], 'name' => $r['name']];
            }
        }

        // Remove stale IDMS ROLE_* realm mappings that are no longer assigned
        $currentResp = Http::withToken($accessToken)
            ->timeout($this->timeout)
            ->get("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$userId}/role-mappings/realm");

        if ($currentResp->successful()) {
            $toRemove = collect($currentResp->json() ?? [])
                ->filter(fn ($r) => is_string($r['name'] ?? null)
                    && str_starts_with($r['name'], 'ROLE_')
                    && !in_array($r['name'], $roleCodes, true))
                ->map(fn ($r) => ['id' => $r['id'], 'name' => $r['name']])
                ->values()
                ->all();

            if (!empty($toRemove)) {
                Http::withToken($accessToken)
                    ->timeout($this->timeout)
                    ->delete("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$userId}/role-mappings/realm", $toRemove);
            }
        }

        if (!empty($mappedRoles)) {
            $assignResp = Http::withToken($accessToken)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$userId}/role-mappings/realm", $mappedRoles);

            if (!$assignResp->successful()) {
                return $this->failureResult('KEYCLOAK_ROLE_ASSIGN_FAILED', 'Failed to assign realm roles in Keycloak.');
            }
        }

        return [
            'success' => true,
            'is_mock' => false,
            'keycloak_sub' => $userId,
            'roles' => array_values($roleCodes),
            'idp_sync_status' => User::IDP_SYNC_LINKED,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    protected function buildAttributes(User $user, ?array $scopeProfile): array
    {
        $scope = $scopeProfile ?: $user->getAdministrativeScope();

        return [
            'tier_level' => [$scope['tier_level'] ?? 'NATIONAL'],
            'ancestry_path' => [$scope['ancestry_path'] ?? '/'],
            'scope_id' => [($scope['scope_id'] ?? $user->administrative_unit_id ?? '')],
            'designation' => [$user->designation ?? ''],
            'employee_id' => [$user->employee_id ?? ''],
        ];
    }

    protected function failureResult(string $code, string $message, ?User $user = null): array
    {
        return [
            'success' => false,
            'is_mock' => false,
            'keycloak_sub' => $user?->keycloak_sub,
            'idp_sync_status' => User::IDP_SYNC_FAILED,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }
}
