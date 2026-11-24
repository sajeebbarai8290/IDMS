<?php

namespace App\Core\Http\Controllers\Api\V1;

use App\Core\Services\AdministrativeScopeService;
use App\Core\Services\UserSynchronizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Class AuthController
 *
 * Provides authenticated profile retrieval, OIDC synchronization,
 * and organizational jurisdiction scope verification.
 *
 * @package App\Core\Http\Controllers\Api\V1
 */
class AuthController extends Controller
{
    public function __construct(
        protected UserSynchronizationService $syncService,
        protected AdministrativeScopeService $scopeService
    ) {}

    /**
     * Retrieve authenticated officer's profile, roles, and administrative scope.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'keycloak_sub' => $user->keycloak_sub,
                'email' => $user->email,
                'name' => $user->full_name_en,
                'role' => $user->role_code,
                'designation' => $user->designation,
                'is_active' => $user->is_active,
                'administrative_scope' => $user->getAdministrativeScope(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Explicitly synchronize user profile from JWT token claims.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('jwt_claims', []);
        $user = $this->syncService->syncFromClaims($claims);

        return response()->json([
            'success' => true,
            'message' => 'User profile and administrative jurisdiction synchronized successfully.',
            'user' => [
                'id' => $user->id,
                'keycloak_sub' => $user->keycloak_sub,
                'email' => $user->email,
                'name' => $user->full_name_en,
                'role' => $user->role_code,
                'administrative_scope' => $user->getAdministrativeScope(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Test organizational scope access for a target administrative node.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkScope(Request $request): JsonResponse
    {
        $request->validate([
            'target_path' => 'required|string',
        ]);

        $user = $request->user();
        $targetPath = $request->query('target_path', $request->input('target_path'));
        $hasAccess = $this->scopeService->canAccessScope($user, $targetPath);

        return response()->json([
            'success' => $hasAccess,
            'target_path' => $targetPath,
            'officer_path' => $user->getAdministrativeScope()['ancestry_path'] ?? '/',
            'has_access' => $hasAccess,
            'reason' => $hasAccess ? 'Target within officer subtree or national jurisdiction.' : 'Target is outside officer jurisdiction (Lateral or Upward Boundary Enforced).',
            'timestamp' => now()->toIso8601String(),
        ], $hasAccess ? 200 : 403);
    }
}
