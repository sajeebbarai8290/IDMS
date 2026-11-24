<?php

use App\Core\Http\Controllers\Api\V1\AdministrativeUnitController;
use App\Core\Http\Controllers\Api\V1\AuthController;
use App\Core\Http\Controllers\Api\V1\HealthController;
use App\Modules\Beneficiary\Controllers\Api\V1\BeneficiaryDashboardController;
use App\Modules\Beneficiary\Controllers\Api\V1\DuplicateDetectionController;
use App\Modules\Beneficiary\Controllers\Api\V1\EligibilityController;
use App\Modules\Beneficiary\Controllers\Api\V1\HouseholdController;
use App\Modules\Beneficiary\Controllers\Api\V1\HouseholdMemberController;
use App\Modules\Beneficiary\Controllers\Api\V1\ImportExportController;
use App\Modules\Beneficiary\Controllers\Api\V1\NidVerificationController;
use App\Modules\Beneficiary\Controllers\Api\V1\VerificationController;
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

        // 1. Authenticated Profile & Synchronization
        Route::get('/auth/user', [AuthController::class, 'user'])->name('api.v1.auth.user');
        Route::post('/auth/sync', [AuthController::class, 'sync'])->name('api.v1.auth.sync');
        Route::get('/auth/scope-check', [AuthController::class, 'checkScope'])->name('api.v1.auth.scope_check');

        // 2. Administrative Hierarchy & Disasters Reference Data
        Route::get('/administrative-units', [AdministrativeUnitController::class, 'index'])->name('api.v1.admin_units');
        Route::get('/disasters', [AdministrativeUnitController::class, 'disasters'])->name('api.v1.disasters');

        // 3. Module 01: Beneficiary Management (beneficiary.*)
        Route::prefix('beneficiary')->group(function () {

            // Dashboard & Analytics
            Route::get('/dashboard/metrics', [BeneficiaryDashboardController::class, 'metrics'])->name('api.v1.beneficiary.dashboard.metrics');
            Route::get('/dashboard/geo-breakdown', [BeneficiaryDashboardController::class, 'geoBreakdown'])->name('api.v1.beneficiary.dashboard.geo');
            Route::get('/dashboard/disaster-breakdown', [BeneficiaryDashboardController::class, 'disasterBreakdown'])->name('api.v1.beneficiary.dashboard.disaster');

            // Import & Export
            Route::post('/import', [ImportExportController::class, 'import'])->name('api.v1.beneficiary.import');
            Route::get('/export', [ImportExportController::class, 'export'])->name('api.v1.beneficiary.export');
            Route::get('/template', [ImportExportController::class, 'template'])->name('api.v1.beneficiary.template');

            // National ID Verification Gateway / Mock Adapter
            Route::post('/verify-nid', [NidVerificationController::class, 'verify'])->name('api.v1.beneficiary.verify_nid');

            // Duplicate Detection Queue & Resolution
            Route::get('/duplicates', [DuplicateDetectionController::class, 'index'])->name('api.v1.beneficiary.duplicates.index');
            Route::post('/duplicates/{suspect}/resolve', [DuplicateDetectionController::class, 'resolve'])->name('api.v1.beneficiary.duplicates.resolve');

            // Household Master CRUD & PII Reveal
            Route::get('/households', [HouseholdController::class, 'index'])->name('api.v1.beneficiary.households.index');
            Route::post('/households', [HouseholdController::class, 'store'])->name('api.v1.beneficiary.households.store');
            Route::get('/households/{household}', [HouseholdController::class, 'show'])->name('api.v1.beneficiary.households.show');
            Route::put('/households/{household}', [HouseholdController::class, 'update'])->name('api.v1.beneficiary.households.update');
            Route::delete('/households/{household}', [HouseholdController::class, 'destroy'])->name('api.v1.beneficiary.households.destroy');
            Route::post('/households/{household}/reveal-pii', [HouseholdController::class, 'revealPii'])->name('api.v1.beneficiary.households.reveal_pii');

            // Household Members Management
            Route::post('/households/{household}/members', [HouseholdMemberController::class, 'store'])->name('api.v1.beneficiary.members.store');
            Route::delete('/households/{household}/members/{member}', [HouseholdMemberController::class, 'destroy'])->name('api.v1.beneficiary.members.destroy');

            // Multi-Stage Verification Workflow
            Route::post('/households/{household}/verify-step', [VerificationController::class, 'verifyStep'])->name('api.v1.beneficiary.verify_step');
            Route::post('/households/{household}/approval', [VerificationController::class, 'finalApproval'])->name('api.v1.beneficiary.approval');

            // Disaster Eligibility Linking & Assistance Delivery
            Route::post('/households/{household}/disaster-eligibility', [EligibilityController::class, 'linkDisaster'])->name('api.v1.beneficiary.disaster.link');
            Route::post('/households/{household}/disaster-eligibility/{eligibility}/disburse', [EligibilityController::class, 'recordAssistance'])->name('api.v1.beneficiary.disaster.disburse');
        });

        // Administrative Overview Scenarios
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
