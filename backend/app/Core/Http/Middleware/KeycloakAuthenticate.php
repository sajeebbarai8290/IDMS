<?php

namespace App\Core\Http\Middleware;

use App\Core\Models\User;
use App\Core\Security\KeycloakJwtService;
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
 * Hydrates authenticated user context without storing or checking Laravel passwords.
 *
 * @package App\Core\Http\Middleware
 */
class KeycloakAuthenticate
{
    public function __construct(
        protected KeycloakJwtService $jwtService
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

        // Hydrate or locate user
        $sub = $claims['sub'] ?? null;
        if (!$sub) {
            throw new AuthenticationException('Token missing required Subject (sub) claim.');
        }

        $email = $claims['email'] ?? $claims['preferred_username'] ?? 'unknown@modmr.gov.bd';
        $name = $claims['name'] ?? $claims['preferred_username'] ?? 'Officer';
        $roles = $claims['roles'] ?? $claims['realm_access']['roles'] ?? [];
        $primaryRole = $roles[0] ?? 'ROLE_FIELD_ENUMERATOR';

        // Instantiate transient authenticated user representation
        $user = new User([
            'id' => $sub,
            'keycloak_sub' => $sub,
            'email' => $email,
            'full_name_en' => $name,
            'role_code' => $primaryRole,
            'is_active' => true,
        ]);

        $user->setJwtClaims($claims);

        // Bind user to request and Auth facade
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('jwt_claims', $claims);

        // Optional Role Gate check
        if ($requiredRole && !$user->hasRole($requiredRole)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ROLE_FORBIDDEN',
                    'message' => "Access denied. Required role '{$requiredRole}' not possessed.",
                ],
                'timestamp' => now()->toIso8601String(),
            ], 403);
        }

        return $next($request);
    }
}
