<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RequireRole
 *
 * Enforces Role-Based Access Control on protected routes.
 * Syntax: middleware('role:ROLE_UNO|ROLE_DRRO')
 *
 * @package App\Core\Http\Middleware
 */
class RequireRole
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$roles
     * @return Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 401);
        }

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            $individualRoles = explode('|', $role);
            foreach ($individualRoles as $r) {
                if ($user->hasRole(trim($r))) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ROLE_FORBIDDEN',
                'message' => 'Access denied. You do not possess the required system role for this resource.',
                'required_roles' => $roles,
                'current_role' => $user->role_code,
            ],
            'timestamp' => now()->toIso8601String(),
        ], 403);
    }
}
