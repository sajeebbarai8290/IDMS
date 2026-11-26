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
use App\Modules\Disaster\Controllers\Api\V1\DisasterCategoryController;
use App\Modules\Disaster\Controllers\Api\V1\DisasterController;
use App\Modules\NidVerification\Controllers\Api\V1\NidVerificationController as ModuleNidVerificationController;
use App\Modules\Project\Controllers\Api\V1\ProjectController;
use App\Modules\Project\Controllers\Api\V1\ProjectDashboardController;
use App\Modules\Project\Controllers\Api\V1\ProjectFinancialController;
use App\Modules\Project\Controllers\Api\V1\ProjectMilestoneController;
use App\Modules\Project\Controllers\Api\V1\ProjectMonitoringController;
use App\Modules\Relief\Controllers\Api\V1\ReliefAllocationController;
use App\Modules\Relief\Controllers\Api\V1\ReliefCategoryController;
use App\Modules\Relief\Controllers\Api\V1\ReliefDashboardController;
use App\Modules\Relief\Controllers\Api\V1\ReliefDistributionController;
use App\Modules\Relief\Controllers\Api\V1\ReliefRequisitionController;
use App\Modules\Response\Controllers\Api\V1\ResponseCoordinationController;
use App\Modules\Response\Controllers\Api\V1\ResponseDashboardController;
use App\Modules\Response\Controllers\Api\V1\ResponseOperationController;
use App\Modules\Response\Controllers\Api\V1\ResponseSituationReportController;
use App\Modules\Response\Controllers\Api\V1\ResponseTaskController;
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

        // 4. Module 02: National ID Verification (nid.*)
        Route::prefix('nid')->group(function () {
            Route::get('/dashboard', [ModuleNidVerificationController::class, 'dashboard'])->name('api.v1.nid.dashboard');
            Route::get('/provider-health', [ModuleNidVerificationController::class, 'providerHealth'])->name('api.v1.nid.provider_health');
            Route::get('/verifications', [ModuleNidVerificationController::class, 'index'])->name('api.v1.nid.verifications.index');
            Route::post('/verifications', [ModuleNidVerificationController::class, 'store'])->name('api.v1.nid.verifications.store');
            Route::get('/verifications/{verification}', [ModuleNidVerificationController::class, 'show'])->name('api.v1.nid.verifications.show');
            Route::post('/verifications/{verification}/retry', [ModuleNidVerificationController::class, 'retry'])->name('api.v1.nid.verifications.retry');
            Route::post('/verifications/{verification}/manual-review', [ModuleNidVerificationController::class, 'manualReview'])->name('api.v1.nid.verifications.manual_review');
        });

        // 5. Module 03: Disaster Information Management (disaster.*)
        Route::prefix('disaster')->group(function () {
            Route::get('/dashboard', [DisasterController::class, 'dashboard'])->name('api.v1.disaster.dashboard');
            Route::get('/gis/geojson', [DisasterController::class, 'geojson'])->name('api.v1.disaster.geojson');
            Route::get('/categories', [DisasterCategoryController::class, 'index'])->name('api.v1.disaster.categories.index');
            Route::post('/categories', [DisasterCategoryController::class, 'store'])->name('api.v1.disaster.categories.store');
            Route::get('/warnings', [DisasterController::class, 'warnings'])->name('api.v1.disaster.warnings.index');

            Route::get('/events', [DisasterController::class, 'index'])->name('api.v1.disaster.events.index');
            Route::post('/events', [DisasterController::class, 'store'])->name('api.v1.disaster.events.store');
            Route::get('/events/{event}', [DisasterController::class, 'show'])->name('api.v1.disaster.events.show');
            Route::put('/events/{event}', [DisasterController::class, 'update'])->name('api.v1.disaster.events.update');
            Route::delete('/events/{event}', [DisasterController::class, 'destroy'])->name('api.v1.disaster.events.destroy');
            Route::put('/events/{event}/impact', [DisasterController::class, 'updateImpact'])->name('api.v1.disaster.events.impact');
            Route::post('/events/{event}/boundary', [DisasterController::class, 'uploadBoundary'])->name('api.v1.disaster.events.boundary');
            Route::post('/events/{event}/lifecycle', [DisasterController::class, 'transitionLifecycle'])->name('api.v1.disaster.events.lifecycle');
            Route::post('/events/{event}/warnings', [DisasterController::class, 'issueWarning'])->name('api.v1.disaster.events.warnings');
            Route::post('/events/{event}/timelines', [DisasterController::class, 'addTimeline'])->name('api.v1.disaster.events.timelines');
            Route::get('/events/{event}/timelines/{timeline}/attachment', [DisasterController::class, 'downloadAttachment'])->name('api.v1.disaster.events.timelines.download');
        });

        // 6. Module 04: Relief Distribution Management (relief.*)
        Route::prefix('relief')->group(function () {
            Route::get('/dashboard', [ReliefDashboardController::class, 'summary'])->name('api.v1.relief.dashboard');
            Route::get('/lookups', [ReliefDashboardController::class, 'lookups'])->name('api.v1.relief.lookups');
            Route::get('/eligible-households', [ReliefDashboardController::class, 'eligibleHouseholds'])->name('api.v1.relief.eligible-households');

            Route::get('/categories', [ReliefCategoryController::class, 'index'])->name('api.v1.relief.categories.index');
            Route::post('/categories', [ReliefCategoryController::class, 'store'])->name('api.v1.relief.categories.store');
            Route::put('/categories/{category}', [ReliefCategoryController::class, 'update'])->name('api.v1.relief.categories.update');

            Route::get('/requisitions', [ReliefRequisitionController::class, 'index'])->name('api.v1.relief.requisitions.index');
            Route::post('/requisitions', [ReliefRequisitionController::class, 'store'])->name('api.v1.relief.requisitions.store');
            Route::get('/requisitions/{requisition}', [ReliefRequisitionController::class, 'show'])->name('api.v1.relief.requisitions.show');
            Route::put('/requisitions/{requisition}', [ReliefRequisitionController::class, 'update'])->name('api.v1.relief.requisitions.update');
            Route::delete('/requisitions/{requisition}', [ReliefRequisitionController::class, 'destroy'])->name('api.v1.relief.requisitions.destroy');
            Route::post('/requisitions/{requisition}/submit', [ReliefRequisitionController::class, 'submit'])->name('api.v1.relief.requisitions.submit');
            Route::post('/requisitions/{requisition}/review', [ReliefRequisitionController::class, 'review'])->name('api.v1.relief.requisitions.review');
            Route::post('/requisitions/{requisition}/approve', [ReliefRequisitionController::class, 'approve'])->name('api.v1.relief.requisitions.approve');
            Route::post('/requisitions/{requisition}/reject', [ReliefRequisitionController::class, 'reject'])->name('api.v1.relief.requisitions.reject');
            Route::get('/requisitions/{requisition}/document', [ReliefRequisitionController::class, 'downloadSupportingDoc'])->name('api.v1.relief.requisitions.document');

            Route::get('/allocations', [ReliefAllocationController::class, 'index'])->name('api.v1.relief.allocations.index');
            Route::post('/allocations', [ReliefAllocationController::class, 'store'])->name('api.v1.relief.allocations.store');
            Route::get('/allocations/{allocation}', [ReliefAllocationController::class, 'show'])->name('api.v1.relief.allocations.show');
            Route::post('/allocations/{allocation}/beneficiaries', [ReliefAllocationController::class, 'assignBeneficiaries'])->name('api.v1.relief.allocations.beneficiaries');
            Route::post('/allocations/{allocation}/approve', [ReliefAllocationController::class, 'approve'])->name('api.v1.relief.allocations.approve');
            Route::post('/allocations/{allocation}/dispatch', [ReliefAllocationController::class, 'dispatchOrder'])->name('api.v1.relief.allocations.dispatch');
            Route::post('/allocations/{allocation}/cancel', [ReliefAllocationController::class, 'cancel'])->name('api.v1.relief.allocations.cancel');

            Route::get('/distributions', [ReliefDistributionController::class, 'index'])->name('api.v1.relief.distributions.index');
            Route::get('/distributions/check-duplicate', [ReliefDistributionController::class, 'checkDuplicate'])->name('api.v1.relief.distributions.check-duplicate');
            Route::get('/distributions/export-muster', [ReliefDistributionController::class, 'exportMuster'])->name('api.v1.relief.distributions.export-muster');
            Route::post('/distributions', [ReliefDistributionController::class, 'store'])->name('api.v1.relief.distributions.store');
            Route::post('/distributions/{voucher}/verify', [ReliefDistributionController::class, 'verify'])->name('api.v1.relief.distributions.verify');
            Route::post('/distributions/{voucher}/void', [ReliefDistributionController::class, 'void'])->name('api.v1.relief.distributions.void');
            Route::get('/distributions/{voucher}/photo', [ReliefDistributionController::class, 'downloadPhotoEvidence'])->name('api.v1.relief.distributions.photo');
        });

        // 7. Module 05: Disaster Response Management (response.*)
        Route::prefix('response')->group(function () {
            Route::get('/dashboard', [ResponseDashboardController::class, 'summary'])->name('api.v1.response.dashboard');
            Route::get('/lookups', [ResponseDashboardController::class, 'lookups'])->name('api.v1.response.lookups');
            Route::get('/gis/geojson', [ResponseDashboardController::class, 'geojson'])->name('api.v1.response.gis.geojson');
            Route::post('/activities', [ResponseDashboardController::class, 'storeActivity'])->name('api.v1.response.activities.store');

            Route::get('/operations', [ResponseOperationController::class, 'index'])->name('api.v1.response.operations.index');
            Route::post('/operations', [ResponseOperationController::class, 'store'])->name('api.v1.response.operations.store');
            Route::get('/operations/{operation}', [ResponseOperationController::class, 'show'])->name('api.v1.response.operations.show');
            Route::put('/operations/{operation}', [ResponseOperationController::class, 'update'])->name('api.v1.response.operations.update');
            Route::delete('/operations/{operation}', [ResponseOperationController::class, 'destroy'])->name('api.v1.response.operations.destroy');
            Route::post('/operations/{operation}/lifecycle', [ResponseOperationController::class, 'transitionLifecycle'])->name('api.v1.response.operations.lifecycle');

            Route::post('/operations/{operation}/agencies', [ResponseCoordinationController::class, 'addAgency'])->name('api.v1.response.operations.agencies.store');
            Route::post('/operations/{operation}/teams', [ResponseCoordinationController::class, 'addTeam'])->name('api.v1.response.operations.teams.store');
            Route::put('/teams/{team}', [ResponseCoordinationController::class, 'updateTeamStatus'])->name('api.v1.response.teams.update');

            Route::post('/operations/{operation}/resources', [ResponseCoordinationController::class, 'addResource'])->name('api.v1.response.operations.resources.store');
            Route::put('/resources/{allocation}', [ResponseCoordinationController::class, 'updateResourceStatus'])->name('api.v1.response.resources.update');

            Route::post('/operations/{operation}/tasks', [ResponseTaskController::class, 'store'])->name('api.v1.response.operations.tasks.store');
            Route::put('/tasks/{task}', [ResponseTaskController::class, 'updateProgress'])->name('api.v1.response.tasks.update');
            Route::post('/tasks/{task}/attachments', [ResponseTaskController::class, 'uploadAttachment'])->name('api.v1.response.tasks.attachments.store');
            Route::get('/tasks/{task}/attachments/{attachment}', [ResponseTaskController::class, 'downloadAttachment'])->name('api.v1.response.tasks.attachments.download');

            Route::get('/reports', [ResponseSituationReportController::class, 'index'])->name('api.v1.response.reports.index');
            Route::post('/operations/{operation}/reports', [ResponseSituationReportController::class, 'store'])->name('api.v1.response.operations.reports.store');
        });

        // 8. Module 06: Project Management (project.*)
        Route::prefix('project')->group(function () {
            Route::get('/dashboard', [ProjectDashboardController::class, 'summary'])->name('api.v1.project.dashboard');
            Route::get('/lookups', [ProjectDashboardController::class, 'lookups'])->name('api.v1.project.lookups');
            Route::get('/gis/geojson', [ProjectDashboardController::class, 'geojson'])->name('api.v1.project.gis.geojson');
            Route::get('/reports', [ProjectDashboardController::class, 'reports'])->name('api.v1.project.reports');
            Route::get('/export', [ProjectDashboardController::class, 'export'])->name('api.v1.project.export');
            Route::post('/categories', [ProjectDashboardController::class, 'storeCategory'])->name('api.v1.project.categories.store');

            Route::get('/schemes', [ProjectController::class, 'index'])->name('api.v1.project.schemes.index');
            Route::post('/schemes', [ProjectController::class, 'store'])->name('api.v1.project.schemes.store');
            Route::get('/schemes/{project}', [ProjectController::class, 'show'])->name('api.v1.project.schemes.show');
            Route::put('/schemes/{project}', [ProjectController::class, 'update'])->name('api.v1.project.schemes.update');
            Route::delete('/schemes/{project}', [ProjectController::class, 'destroy'])->name('api.v1.project.schemes.destroy');
            Route::post('/schemes/{project}/lifecycle', [ProjectController::class, 'transitionLifecycle'])->name('api.v1.project.schemes.lifecycle');
            Route::post('/schemes/{project}/boundary', [ProjectController::class, 'uploadBoundary'])->name('api.v1.project.schemes.boundary');

            Route::post('/schemes/{project}/milestones', [ProjectMilestoneController::class, 'store'])->name('api.v1.project.schemes.milestones.store');
            Route::put('/milestones/{milestone}/progress', [ProjectMilestoneController::class, 'updateProgress'])->name('api.v1.project.milestones.progress');
            Route::post('/milestones/{milestone}/certify', [ProjectMilestoneController::class, 'certify'])->name('api.v1.project.milestones.certify');

            Route::post('/schemes/{project}/financials', [ProjectFinancialController::class, 'store'])->name('api.v1.project.schemes.financials.store');
            Route::post('/financials/{disbursement}/approve', [ProjectFinancialController::class, 'approve'])->name('api.v1.project.financials.approve');
            Route::get('/financials/{disbursement}/document', [ProjectFinancialController::class, 'downloadDocument'])->name('api.v1.project.financials.document');

            Route::post('/schemes/{project}/monitoring-reports', [ProjectMonitoringController::class, 'storeReport'])->name('api.v1.project.schemes.monitoring.store');
            Route::get('/monitoring-reports/{report}/attachment', [ProjectMonitoringController::class, 'downloadAttachment'])->name('api.v1.project.monitoring.attachment');
            Route::post('/schemes/{project}/risks', [ProjectMonitoringController::class, 'storeRisk'])->name('api.v1.project.schemes.risks.store');
            Route::put('/risks/{risk}', [ProjectMonitoringController::class, 'updateRisk'])->name('api.v1.project.risks.update');
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
