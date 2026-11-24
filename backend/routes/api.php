<?php

use App\Core\Http\Controllers\Api\V1\AuthController;
use App\Core\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IDMS REST API Routes - Version 1 (/api/v1)
|--------------------------------------------------------------------------
|
| Versioning: /api/v1
| Authentication: Keycloak Bearer Tokens (RS256 JWT)
| Authorization: Sovereign RBAC & 7-Tier Hierarchical Scoping
|
*/

Route::prefix('v1')->group(function () {

    // Public / Inspection Endpoints
    Route::get('/health', [HealthController::class, 'check'])->name('api.v1.health');
    Route::get('/system/info', [HealthController::class, 'systemInfo'])->name('api.v1.system.info');

    // Keycloak Protected API Routes
    Route::middleware('keycloak.auth')->group(function () {
        // Authenticated Profile & Synchronization
        Route::get('/auth/user', [AuthController::class, 'user'])->name('api.v1.auth.user');
        Route::post('/auth/sync', [AuthController::class, 'sync'])->name('api.v1.auth.sync');
        Route::get('/auth/scope-check', [AuthController::class, 'checkScope'])->name('api.v1.auth.scope_check');

        // Role-Protected Administrative Scenarios
        Route::middleware('role:ROLE_SUPER_ADMIN|ROLE_MINISTRY_EXECUTIVE')->group(function () {
            Route::get('/admin/overview', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'National Executive Overview. Authorized for Super Admin & Ministry Executives only.',
                    'timestamp' => now()->toIso8601String(),
                ]);
            });
        });

        Route::middleware('role:ROLE_UNO|ROLE_PIO|ROLE_SUPER_ADMIN')->group(function () {
            Route::get('/upazila/operations', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Upazila Operational Panel. Authorized for UNO & PIO.',
                    'timestamp' => now()->toIso8601String(),
                ]);
            });
        });
    });

});
