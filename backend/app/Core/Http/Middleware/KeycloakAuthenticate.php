<?php

namespace App\Core\Http\Middleware;

use App\Core\Security\KeycloakJwtService;
use App\Core\Services\UserSynchronizationService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Class KeycloakAuthenticate
 *
 * Enforces Keycloak Bearer token authentication on protected API routes.
 * Synchronizes user profiles and hydrates authenticated user context without storing passwords in Laravel.
 *
 * @package App\Core\Http\Middleware
 */
class KeycloakAuthenticate
{
    public function __construct(
        protected KeycloakJwtService $jwtService,
        protected UserSynchronizationService $syncService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $requiredRole
     * @return Response
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            throw new AuthenticationException('Bearer token missing from Authorization header.');
        }

        try {
            $claims = $this->jwtService->validateToken($token);
        } catch (Throwable $e) {
            throw new AuthenticationException('Token validation failed: ' . $e->getMessage(), [], $e);
        }

        $sub = $claims['sub'] ?? null;
        if (!$sub) {
            throw new AuthenticationException('Token missing required Subject (sub) claim.');
        }

        // Synchronize or retrieve local user profile
        try {
            $user = $this->syncService->syncFromClaims($claims);
        } catch (Throwable $e) {
            // Fallback to transient user if database write is deferred
            $email = $claims['email'] ?? ($claims['preferred_username'] . '@modmr.gov.bd');
            $user = new \App\Core\Models\User([
                'id' => $sub,
                'keycloak_sub' => $sub,
                'email' => $email,
                'full_name_en' => $claims['name'] ?? $claims['preferred_username'] ?? 'Officer',
                'role_code' => $claims['roles'][0] ?? 'ROLE_FIELD_ENUMERATOR',
                'is_active' => true,
            ]);
            $user->setJwtClaims($claims);
        }

        // Bind user to request and Auth facade
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('jwt_claims', $claims);
        $request->attributes->set('administrative_scope', $user->getAdministrativeScope());

        // Optional Role Gate check
        if ($requiredRole) {
            $allowedRoles = explode('|', $requiredRole);
            $hasAllowedRole = false;
            foreach ($allowedRoles as $role) {
                if ($user->hasRole(trim($role))) {
                    $hasAllowedRole = true;
                    break;
                }
            }

            if (!$hasAllowedRole) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ROLE_FORBIDDEN',
                        'message' => "Access denied. Required role '{$requiredRole}' not possessed.",
                    ],
                    'timestamp' => now()->toIso8601String(),
                ], 403);
            }
        }

        return $next($request);
    }
}
