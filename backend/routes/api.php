<?php

use App\Core\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IDMS REST API Routes - Version 1 (/api/v1)
|--------------------------------------------------------------------------
|
| All API endpoints adhere to BNDA standards and IDMS Architecture.
| Versioning: /api/v1
| Authentication: Keycloak Bearer Tokens (RS256 JWT)
|
*/

Route::prefix('v1')->group(function () {

    // Public / System Inspection Endpoints
    Route::get('/health', [HealthController::class, 'check'])->name('api.v1.health');
    Route::get('/system/info', [HealthController::class, 'systemInfo'])->name('api.v1.system.info');

    // Keycloak Protected Endpoints
    Route::middleware('keycloak.auth')->group(function () {
        Route::get('/auth/status', [HealthController::class, 'authStatus'])->name('api.v1.auth.status');
    });

});
