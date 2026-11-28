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
use App\Modules\DamageAssessment\Controllers\Api\V1\DamageAssessmentController;
use App\Modules\DamageAssessment\Controllers\Api\V1\DamageDashboardController;
use App\Modules\DamageAssessment\Controllers\Api\V1\DamageVerificationController;
use App\Modules\Disaster\Controllers\Api\V1\DisasterCategoryController;
use App\Modules\Disaster\Controllers\Api\V1\DisasterController;
use App\Modules\Document\Controllers\Api\V1\DocumentCategoryController;
use App\Modules\Document\Controllers\Api\V1\DocumentController;
use App\Modules\Document\Controllers\Api\V1\DocumentDashboardController;
use App\Modules\NidVerification\Controllers\Api\V1\NidVerificationController as ModuleNidVerificationController;
use App\Modules\Notification\Controllers\Api\V1\AlertController;
use App\Modules\Notification\Controllers\Api\V1\NotificationDashboardController;
use App\Modules\Notification\Controllers\Api\V1\NotificationDispatchController;
use App\Modules\Notification\Controllers\Api\V1\NotificationTemplateController;
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
use App\Modules\Resource\Controllers\Api\V1\HumanResourceDeploymentController;
use App\Modules\Resource\Controllers\Api\V1\ResourceAllocationController;
use App\Modules\Resource\Controllers\Api\V1\ResourceAssetController;
use App\Modules\Resource\Controllers\Api\V1\ResourceDashboardController;
use App\Modules\Resource\Controllers\Api\V1\ResourceMaintenanceController;
use App\Modules\Response\Controllers\Api\V1\ResponseCoordinationController;
use App\Modules\Response\Controllers\Api\V1\ResponseDashboardController;
use App\Modules\Response\Controllers\Api\V1\ResponseOperationController;
use App\Modules\Response\Controllers\Api\V1\ResponseSituationReportController;
use App\Modules\Response\Controllers\Api\V1\ResponseTaskController;
use App\Modules\UserManagement\Controllers\Api\V1\OrganizationController;
use App\Modules\UserManagement\Controllers\Api\V1\RoleController;
use App\Modules\UserManagement\Controllers\Api\V1\UserController;
use App\Modules\UserManagement\Controllers\Api\V1\UserDashboardController;
use App\Modules\Volunteer\Controllers\Api\V1\VolunteerAttendanceController;
use App\Modules\Volunteer\Controllers\Api\V1\VolunteerCategoryController;
use App\Modules\Volunteer\Controllers\Api\V1\VolunteerController;
use App\Modules\Volunteer\Controllers\Api\V1\VolunteerDashboardController;
use App\Modules\Volunteer\Controllers\Api\V1\VolunteerDeploymentController;
use App\Modules\Volunteer\Controllers\Api\V1\VolunteerOrganizationController;
use App\Modules\Volunteer\Controllers\Api\V1\VolunteerTrainingController;
use App\Modules\Warehouse\Controllers\Api\V1\StockReceiptController;
use App\Modules\Warehouse\Controllers\Api\V1\StockTransactionController;
use App\Modules\Warehouse\Controllers\Api\V1\WarehouseDashboardController;
use App\Modules\Warehouse\Controllers\Api\V1\WarehouseFacilityController;
use App\Modules\Warehouse\Controllers\Api\V1\WarehouseItemController;
use App\Modules\Warehouse\Controllers\Api\V1\WarehouseReliefIntegrationController;
use App\Modules\Warehouse\Controllers\Api\V1\WarehouseTransferController;
use App\Modules\Workflow\Controllers\Api\V1\WorkflowConfigurationController;
use App\Modules\Workflow\Controllers\Api\V1\WorkflowDashboardController;
use App\Modules\Workflow\Controllers\Api\V1\WorkflowDelegationController;
use App\Modules\Workflow\Controllers\Api\V1\WorkflowInstanceController;
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

        // 9. Module 07: Resource Management (resource.*)
        Route::prefix('resource')->group(function () {
            Route::get('/dashboard', [ResourceDashboardController::class, 'summary'])->name('api.v1.resource.dashboard');
            Route::get('/lookups', [ResourceDashboardController::class, 'lookups'])->name('api.v1.resource.lookups');
            Route::get('/gis/geojson', [ResourceDashboardController::class, 'geojson'])->name('api.v1.resource.gis.geojson');
            Route::get('/reminders', [ResourceDashboardController::class, 'reminders'])->name('api.v1.resource.reminders');
            Route::get('/export', [ResourceDashboardController::class, 'export'])->name('api.v1.resource.export');
            Route::get('/categories', [ResourceDashboardController::class, 'categories'])->name('api.v1.resource.categories.index');
            Route::post('/categories', [ResourceDashboardController::class, 'storeCategory'])->name('api.v1.resource.categories.store');

            Route::get('/assets', [ResourceAssetController::class, 'index'])->name('api.v1.resource.assets.index');
            Route::post('/assets', [ResourceAssetController::class, 'store'])->name('api.v1.resource.assets.store');
            Route::get('/assets/{asset}', [ResourceAssetController::class, 'show'])->name('api.v1.resource.assets.show');
            Route::put('/assets/{asset}', [ResourceAssetController::class, 'update'])->name('api.v1.resource.assets.update');
            Route::delete('/assets/{asset}', [ResourceAssetController::class, 'destroy'])->name('api.v1.resource.assets.destroy');
            Route::post('/assets/{asset}/attachments', [ResourceAssetController::class, 'uploadAttachment'])->name('api.v1.resource.assets.attachments.store');
            Route::get('/assets/{asset}/attachments/{attachment}', [ResourceAssetController::class, 'downloadAttachment'])->name('api.v1.resource.assets.attachments.download');

            Route::get('/allocations', [ResourceAllocationController::class, 'index'])->name('api.v1.resource.allocations.index');
            Route::post('/allocations', [ResourceAllocationController::class, 'store'])->name('api.v1.resource.allocations.store');
            Route::post('/allocations/{allocation}/in-use', [ResourceAllocationController::class, 'markInUse'])->name('api.v1.resource.allocations.in_use');
            Route::post('/allocations/{allocation}/release', [ResourceAllocationController::class, 'release'])->name('api.v1.resource.allocations.release');

            Route::get('/maintenances', [ResourceMaintenanceController::class, 'index'])->name('api.v1.resource.maintenances.index');
            Route::post('/assets/{asset}/maintenances', [ResourceMaintenanceController::class, 'store'])->name('api.v1.resource.assets.maintenances.store');
            Route::put('/maintenances/{maintenance}', [ResourceMaintenanceController::class, 'update'])->name('api.v1.resource.maintenances.update');
            Route::get('/maintenances/{maintenance}/attachment', [ResourceMaintenanceController::class, 'downloadAttachment'])->name('api.v1.resource.maintenances.attachment');

            Route::get('/human-deployments', [HumanResourceDeploymentController::class, 'index'])->name('api.v1.resource.human.index');
            Route::post('/human-deployments', [HumanResourceDeploymentController::class, 'store'])->name('api.v1.resource.human.store');
            Route::post('/human-deployments/{deployment}/release', [HumanResourceDeploymentController::class, 'release'])->name('api.v1.resource.human.release');
        });

        // 10. Module 08: Warehouse & Inventory Management (warehouse.*)
        Route::prefix('warehouse')->group(function () {
            Route::get('/dashboard', [WarehouseDashboardController::class, 'summary'])->name('api.v1.warehouse.dashboard');
            Route::get('/lookups', [WarehouseDashboardController::class, 'lookups'])->name('api.v1.warehouse.lookups');
            Route::get('/gis/geojson', [WarehouseDashboardController::class, 'geojson'])->name('api.v1.warehouse.gis.geojson');
            Route::get('/alerts', [WarehouseDashboardController::class, 'alerts'])->name('api.v1.warehouse.alerts');
            Route::get('/reports', [WarehouseDashboardController::class, 'reports'])->name('api.v1.warehouse.reports');
            Route::get('/export-ledger', [WarehouseDashboardController::class, 'export'])->name('api.v1.warehouse.export_ledger');

            Route::get('/facilities', [WarehouseFacilityController::class, 'index'])->name('api.v1.warehouse.facilities.index');
            Route::post('/facilities', [WarehouseFacilityController::class, 'store'])->name('api.v1.warehouse.facilities.store');
            Route::get('/facilities/{id}', [WarehouseFacilityController::class, 'show'])->name('api.v1.warehouse.facilities.show');
            Route::put('/facilities/{id}', [WarehouseFacilityController::class, 'update'])->name('api.v1.warehouse.facilities.update');

            Route::get('/items', [WarehouseItemController::class, 'index'])->name('api.v1.warehouse.items.index');
            Route::post('/items', [WarehouseItemController::class, 'store'])->name('api.v1.warehouse.items.store');
            Route::get('/items/{id}', [WarehouseItemController::class, 'show'])->name('api.v1.warehouse.items.show');
            Route::put('/items/{id}', [WarehouseItemController::class, 'update'])->name('api.v1.warehouse.items.update');

            Route::get('/inventories', [StockTransactionController::class, 'inventories'])->name('api.v1.warehouse.inventories.index');
            Route::get('/transactions', [StockTransactionController::class, 'index'])->name('api.v1.warehouse.transactions.index');
            Route::post('/transactions/adjustment', [StockTransactionController::class, 'storeAdjustment'])->name('api.v1.warehouse.transactions.adjustment');

            Route::get('/receipts', [StockReceiptController::class, 'index'])->name('api.v1.warehouse.receipts.index');
            Route::post('/receipts', [StockReceiptController::class, 'store'])->name('api.v1.warehouse.receipts.store');
            Route::get('/receipts/{id}', [StockReceiptController::class, 'show'])->name('api.v1.warehouse.receipts.show');
            Route::post('/receipts/{id}/verify', [StockReceiptController::class, 'verify'])->name('api.v1.warehouse.receipts.verify');
            Route::post('/receipts/{id}/reject', [StockReceiptController::class, 'reject'])->name('api.v1.warehouse.receipts.reject');
            Route::get('/receipts/{id}/attachment', [StockReceiptController::class, 'downloadAttachment'])->name('api.v1.warehouse.receipts.attachment');

            Route::get('/transfers', [WarehouseTransferController::class, 'index'])->name('api.v1.warehouse.transfers.index');
            Route::post('/transfers', [WarehouseTransferController::class, 'store'])->name('api.v1.warehouse.transfers.store');
            Route::get('/transfers/{id}', [WarehouseTransferController::class, 'show'])->name('api.v1.warehouse.transfers.show');
            Route::post('/transfers/{id}/approve', [WarehouseTransferController::class, 'approve'])->name('api.v1.warehouse.transfers.approve');
            Route::post('/transfers/{id}/dispatch', [WarehouseTransferController::class, 'dispatchOrder'])->name('api.v1.warehouse.transfers.dispatch');
            Route::post('/transfers/{id}/receive', [WarehouseTransferController::class, 'receiveOrder'])->name('api.v1.warehouse.transfers.receive');
            Route::post('/transfers/{id}/cancel', [WarehouseTransferController::class, 'cancel'])->name('api.v1.warehouse.transfers.cancel');

            Route::get('/relief-allocations', [WarehouseReliefIntegrationController::class, 'allocations'])->name('api.v1.warehouse.relief.allocations');
            Route::post('/relief-allocations/{allocationId}/issue', [WarehouseReliefIntegrationController::class, 'issueStock'])->name('api.v1.warehouse.relief.issue');
        });

        // 11. Module 09: Damage Assessment - D-Form (damage.*)
        Route::prefix('damage')->group(function () {
            Route::get('/dashboard', [DamageDashboardController::class, 'summary'])->name('api.v1.damage.dashboard');
            Route::get('/lookups', [DamageDashboardController::class, 'lookups'])->name('api.v1.damage.lookups');
            Route::get('/categories', [DamageDashboardController::class, 'categories'])->name('api.v1.damage.categories.index');
            Route::post('/categories', [DamageDashboardController::class, 'storeCategory'])->name('api.v1.damage.categories.store');
            Route::put('/categories/{category}', [DamageDashboardController::class, 'updateCategory'])->name('api.v1.damage.categories.update');

            Route::get('/gis/geojson', [DamageDashboardController::class, 'geojson'])->name('api.v1.damage.gis.geojson');
            Route::get('/gis/heatmap', [DamageDashboardController::class, 'heatmap'])->name('api.v1.damage.gis.heatmap');
            Route::get('/gis/spatial-aggregation', [DamageDashboardController::class, 'spatialAggregation'])->name('api.v1.damage.gis.aggregation');
            Route::get('/reports', [DamageDashboardController::class, 'reports'])->name('api.v1.damage.reports');
            Route::get('/export', [DamageDashboardController::class, 'export'])->name('api.v1.damage.export');

            Route::post('/assessments/sync', [DamageAssessmentController::class, 'sync'])->name('api.v1.damage.assessments.sync');
            Route::get('/assessments', [DamageAssessmentController::class, 'index'])->name('api.v1.damage.assessments.index');
            Route::post('/assessments', [DamageAssessmentController::class, 'store'])->name('api.v1.damage.assessments.store');
            Route::get('/assessments/{assessment}', [DamageAssessmentController::class, 'show'])->name('api.v1.damage.assessments.show');
            Route::put('/assessments/{assessment}', [DamageAssessmentController::class, 'update'])->name('api.v1.damage.assessments.update');
            Route::delete('/assessments/{assessment}', [DamageAssessmentController::class, 'destroy'])->name('api.v1.damage.assessments.destroy');
            Route::post('/assessments/{assessment}/boundary', [DamageAssessmentController::class, 'uploadBoundary'])->name('api.v1.damage.assessments.boundary');

            Route::post('/assessments/{assessment}/attachments', [DamageAssessmentController::class, 'uploadAttachment'])->name('api.v1.damage.assessments.attachments.store');
            Route::get('/assessments/{assessment}/attachments/{attachment}', [DamageAssessmentController::class, 'downloadAttachment'])->name('api.v1.damage.assessments.attachments.download');
            Route::delete('/assessments/{assessment}/attachments/{attachment}', [DamageAssessmentController::class, 'deleteAttachment'])->name('api.v1.damage.assessments.attachments.destroy');

            Route::post('/assessments/{assessment}/submit', [DamageVerificationController::class, 'submit'])->name('api.v1.damage.assessments.submit');
            Route::post('/assessments/{assessment}/start-verification', [DamageVerificationController::class, 'startVerification'])->name('api.v1.damage.assessments.start_verification');
            Route::post('/assessments/{assessment}/request-correction', [DamageVerificationController::class, 'requestCorrection'])->name('api.v1.damage.assessments.request_correction');
            Route::post('/assessments/{assessment}/verify', [DamageVerificationController::class, 'verify'])->name('api.v1.damage.assessments.verify');
            Route::post('/assessments/{assessment}/reject', [DamageVerificationController::class, 'reject'])->name('api.v1.damage.assessments.reject');
            Route::post('/assessments/{assessment}/approve', [DamageVerificationController::class, 'approve'])->name('api.v1.damage.assessments.approve');
        });

        // 12. Module 10: Volunteer Management (volunteer.*)
        Route::prefix('volunteer')->group(function () {
            Route::get('/dashboard', [VolunteerDashboardController::class, 'summary'])->name('api.v1.volunteer.dashboard');
            Route::get('/lookups', [VolunteerDashboardController::class, 'lookups'])->name('api.v1.volunteer.lookups');
            Route::get('/gis/geojson', [VolunteerDashboardController::class, 'geojson'])->name('api.v1.volunteer.gis.geojson');
            Route::get('/reports', [VolunteerDashboardController::class, 'reports'])->name('api.v1.volunteer.reports');
            Route::get('/export', [VolunteerDashboardController::class, 'export'])->name('api.v1.volunteer.export');

            Route::get('/categories', [VolunteerCategoryController::class, 'categories'])->name('api.v1.volunteer.categories.index');
            Route::post('/categories', [VolunteerCategoryController::class, 'storeCategory'])->name('api.v1.volunteer.categories.store');
            Route::put('/categories/{category}', [VolunteerCategoryController::class, 'updateCategory'])->name('api.v1.volunteer.categories.update');
            Route::get('/skills', [VolunteerCategoryController::class, 'skills'])->name('api.v1.volunteer.skills.index');
            Route::post('/skills', [VolunteerCategoryController::class, 'storeSkill'])->name('api.v1.volunteer.skills.store');
            Route::put('/skills/{skill}', [VolunteerCategoryController::class, 'updateSkill'])->name('api.v1.volunteer.skills.update');

            Route::get('/organizations', [VolunteerOrganizationController::class, 'index'])->name('api.v1.volunteer.organizations.index');
            Route::post('/organizations', [VolunteerOrganizationController::class, 'store'])->name('api.v1.volunteer.organizations.store');
            Route::get('/organizations/{organization}', [VolunteerOrganizationController::class, 'show'])->name('api.v1.volunteer.organizations.show');
            Route::put('/organizations/{organization}', [VolunteerOrganizationController::class, 'update'])->name('api.v1.volunteer.organizations.update');
            Route::delete('/organizations/{organization}', [VolunteerOrganizationController::class, 'destroy'])->name('api.v1.volunteer.organizations.destroy');
            Route::post('/organizations/{organization}/verify', [VolunteerOrganizationController::class, 'verify'])->name('api.v1.volunteer.organizations.verify');

            Route::get('/volunteers', [VolunteerController::class, 'index'])->name('api.v1.volunteer.volunteers.index');
            Route::post('/volunteers', [VolunteerController::class, 'store'])->name('api.v1.volunteer.volunteers.store');
            Route::get('/volunteers/{volunteer}', [VolunteerController::class, 'show'])->name('api.v1.volunteer.volunteers.show');
            Route::put('/volunteers/{volunteer}', [VolunteerController::class, 'update'])->name('api.v1.volunteer.volunteers.update');
            Route::delete('/volunteers/{volunteer}', [VolunteerController::class, 'destroy'])->name('api.v1.volunteer.volunteers.destroy');
            Route::post('/volunteers/{volunteer}/photo', [VolunteerController::class, 'uploadPhoto'])->name('api.v1.volunteer.volunteers.photo.upload');
            Route::get('/volunteers/{volunteer}/photo', [VolunteerController::class, 'downloadPhoto'])->name('api.v1.volunteer.volunteers.photo.download');
            Route::post('/volunteers/{volunteer}/reveal-pii', [VolunteerController::class, 'revealPii'])->name('api.v1.volunteer.volunteers.reveal_pii');
            Route::post('/volunteers/{volunteer}/skills', [VolunteerController::class, 'syncSkills'])->name('api.v1.volunteer.volunteers.skills');
            Route::post('/volunteers/{volunteer}/submit', [VolunteerController::class, 'submit'])->name('api.v1.volunteer.volunteers.submit');
            Route::post('/volunteers/{volunteer}/verify-pillar', [VolunteerController::class, 'verifyPillar'])->name('api.v1.volunteer.volunteers.verify_pillar');
            Route::post('/volunteers/{volunteer}/approve', [VolunteerController::class, 'approve'])->name('api.v1.volunteer.volunteers.approve');
            Route::post('/volunteers/{volunteer}/reject', [VolunteerController::class, 'reject'])->name('api.v1.volunteer.volunteers.reject');

            Route::get('/deployments', [VolunteerDeploymentController::class, 'index'])->name('api.v1.volunteer.deployments.index');
            Route::post('/deployments', [VolunteerDeploymentController::class, 'store'])->name('api.v1.volunteer.deployments.store');
            Route::get('/deployments/{deployment}', [VolunteerDeploymentController::class, 'show'])->name('api.v1.volunteer.deployments.show');
            Route::post('/deployments/{deployment}/activate', [VolunteerDeploymentController::class, 'activate'])->name('api.v1.volunteer.deployments.activate');
            Route::post('/deployments/{deployment}/complete', [VolunteerDeploymentController::class, 'complete'])->name('api.v1.volunteer.deployments.complete');
            Route::post('/deployments/{deployment}/cancel', [VolunteerDeploymentController::class, 'cancel'])->name('api.v1.volunteer.deployments.cancel');

            Route::get('/attendances', [VolunteerAttendanceController::class, 'index'])->name('api.v1.volunteer.attendances.index');
            Route::post('/deployments/{deployment}/check-in', [VolunteerAttendanceController::class, 'checkIn'])->name('api.v1.volunteer.attendances.check_in');
            Route::post('/attendances/{attendance}/check-out', [VolunteerAttendanceController::class, 'checkOut'])->name('api.v1.volunteer.attendances.check_out');
            Route::post('/attendances/{attendance}/confirm', [VolunteerAttendanceController::class, 'confirm'])->name('api.v1.volunteer.attendances.confirm');
            Route::post('/attendances/{attendance}/evidence', [VolunteerAttendanceController::class, 'uploadEvidence'])->name('api.v1.volunteer.attendances.evidence.upload');
            Route::get('/attendances/{attendance}/evidence', [VolunteerAttendanceController::class, 'downloadEvidence'])->name('api.v1.volunteer.attendances.evidence.download');

            Route::get('/training-programs', [VolunteerTrainingController::class, 'programs'])->name('api.v1.volunteer.training.programs.index');
            Route::post('/training-programs', [VolunteerTrainingController::class, 'storeProgram'])->name('api.v1.volunteer.training.programs.store');
            Route::put('/training-programs/{program}', [VolunteerTrainingController::class, 'updateProgram'])->name('api.v1.volunteer.training.programs.update');
            Route::get('/training-sessions', [VolunteerTrainingController::class, 'sessions'])->name('api.v1.volunteer.training.sessions.index');
            Route::post('/training-sessions', [VolunteerTrainingController::class, 'storeSession'])->name('api.v1.volunteer.training.sessions.store');
            Route::get('/training-sessions/{session}', [VolunteerTrainingController::class, 'showSession'])->name('api.v1.volunteer.training.sessions.show');
            Route::put('/training-sessions/{session}', [VolunteerTrainingController::class, 'updateSession'])->name('api.v1.volunteer.training.sessions.update');
            Route::post('/training-sessions/{session}/enroll', [VolunteerTrainingController::class, 'enroll'])->name('api.v1.volunteer.training.sessions.enroll');
            Route::put('/training-enrollments/{enrollment}', [VolunteerTrainingController::class, 'updateEnrollment'])->name('api.v1.volunteer.training.enrollments.update');
            Route::post('/training-enrollments/{enrollment}/certificate', [VolunteerTrainingController::class, 'uploadCertificate'])->name('api.v1.volunteer.training.enrollments.certificate.upload');
            Route::get('/training-enrollments/{enrollment}/certificate', [VolunteerTrainingController::class, 'downloadCertificate'])->name('api.v1.volunteer.training.enrollments.certificate.download');
        });

        // 13. Module 11: Workflow Management (workflow.*)
        Route::prefix('workflow')->group(function () {
            Route::get('/dashboard', [WorkflowDashboardController::class, 'summary'])->name('api.v1.workflow.dashboard');
            Route::get('/lookups', [WorkflowDashboardController::class, 'lookups'])->name('api.v1.workflow.lookups');
            Route::get('/target-entities', [WorkflowDashboardController::class, 'targetEntities'])->name('api.v1.workflow.target_entities');

            Route::get('/notifications', [WorkflowDashboardController::class, 'notifications'])->name('api.v1.workflow.notifications.index');
            Route::post('/notifications/{notification}/read', [WorkflowDashboardController::class, 'markNotificationRead'])->name('api.v1.workflow.notifications.read');
            Route::post('/notifications/read-all', [WorkflowDashboardController::class, 'markAllNotificationsRead'])->name('api.v1.workflow.notifications.read_all');
            Route::post('/escalations/run', [WorkflowDashboardController::class, 'runEscalations'])->name('api.v1.workflow.escalations.run');

            Route::get('/definitions', [WorkflowConfigurationController::class, 'index'])->name('api.v1.workflow.definitions.index');
            Route::post('/definitions', [WorkflowConfigurationController::class, 'store'])->name('api.v1.workflow.definitions.store');
            Route::get('/definitions/{definition}', [WorkflowConfigurationController::class, 'show'])->name('api.v1.workflow.definitions.show');
            Route::put('/definitions/{definition}', [WorkflowConfigurationController::class, 'update'])->name('api.v1.workflow.definitions.update');
            Route::post('/definitions/{definition}/toggle-status', [WorkflowConfigurationController::class, 'toggleStatus'])->name('api.v1.workflow.definitions.toggle_status');
            Route::delete('/definitions/{definition}', [WorkflowConfigurationController::class, 'destroy'])->name('api.v1.workflow.definitions.destroy');

            Route::get('/delegations', [WorkflowDelegationController::class, 'index'])->name('api.v1.workflow.delegations.index');
            Route::post('/delegations', [WorkflowDelegationController::class, 'store'])->name('api.v1.workflow.delegations.store');
            Route::post('/delegations/{delegation}/revoke', [WorkflowDelegationController::class, 'revoke'])->name('api.v1.workflow.delegations.revoke');

            Route::get('/entities/{entityType}/{entityId}/history', [WorkflowInstanceController::class, 'entityHistory'])
                ->whereUuid('entityId')
                ->name('api.v1.workflow.entities.history');

            Route::get('/instances', [WorkflowInstanceController::class, 'index'])->name('api.v1.workflow.instances.index');
            Route::post('/instances', [WorkflowInstanceController::class, 'store'])->name('api.v1.workflow.instances.store');
            Route::get('/instances/{instance}', [WorkflowInstanceController::class, 'show'])->name('api.v1.workflow.instances.show');
            Route::post('/instances/{instance}/approve', [WorkflowInstanceController::class, 'approve'])->name('api.v1.workflow.instances.approve');
            Route::post('/instances/{instance}/reject', [WorkflowInstanceController::class, 'reject'])->name('api.v1.workflow.instances.reject');
            Route::post('/instances/{instance}/return-correction', [WorkflowInstanceController::class, 'returnCorrection'])->name('api.v1.workflow.instances.return_correction');
            Route::post('/instances/{instance}/request-info', [WorkflowInstanceController::class, 'requestInfo'])->name('api.v1.workflow.instances.request_info');
            Route::post('/instances/{instance}/provide-info', [WorkflowInstanceController::class, 'provideInfo'])->name('api.v1.workflow.instances.provide_info');
            Route::post('/instances/{instance}/resubmit', [WorkflowInstanceController::class, 'resubmit'])->name('api.v1.workflow.instances.resubmit');
            Route::post('/instances/{instance}/forward', [WorkflowInstanceController::class, 'forward'])->name('api.v1.workflow.instances.forward');
            Route::post('/instances/{instance}/cancel', [WorkflowInstanceController::class, 'cancel'])->name('api.v1.workflow.instances.cancel');
            Route::post('/instances/{instance}/remind', [WorkflowInstanceController::class, 'remind'])->name('api.v1.workflow.instances.remind');
            Route::post('/instances/{instance}/attachments', [WorkflowInstanceController::class, 'uploadAttachment'])->name('api.v1.workflow.instances.attachments.store');
            Route::get('/instances/{instance}/attachments/{attachment}', [WorkflowInstanceController::class, 'downloadAttachment'])->name('api.v1.workflow.instances.attachments.download');
        });

        // 14. Module 12: User Management (user.*, role.*, organization.*)
        Route::prefix('user-management')->group(function () {
            Route::get('/dashboard', [UserDashboardController::class, 'summary'])->name('api.v1.user_management.dashboard');
            Route::get('/lookups', [UserDashboardController::class, 'lookups'])->name('api.v1.user_management.lookups');
            Route::get('/activity-logs', [UserDashboardController::class, 'activityLogs'])->name('api.v1.user_management.activity_logs');
            Route::get('/keycloak-health', [UserDashboardController::class, 'keycloakHealth'])->name('api.v1.user_management.keycloak_health');

            Route::get('/users', [UserController::class, 'index'])->name('api.v1.user_management.users.index');
            Route::post('/users', [UserController::class, 'store'])->name('api.v1.user_management.users.store');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('api.v1.user_management.users.show');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('api.v1.user_management.users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('api.v1.user_management.users.destroy');
            Route::post('/users/{user}/status', [UserController::class, 'changeStatus'])->name('api.v1.user_management.users.status');
            Route::post('/users/{user}/roles', [UserController::class, 'assignRoles'])->name('api.v1.user_management.users.roles');
            Route::post('/users/{user}/scopes', [UserController::class, 'assignScope'])->name('api.v1.user_management.users.scopes.store');
            Route::post('/users/{user}/scopes/{scope}/revoke', [UserController::class, 'revokeScope'])->name('api.v1.user_management.users.scopes.revoke');
            Route::post('/users/{user}/sync-keycloak', [UserController::class, 'syncKeycloak'])->name('api.v1.user_management.users.sync_keycloak');
            Route::post('/users/{user}/photo', [UserController::class, 'uploadPhoto'])->name('api.v1.user_management.users.photo.upload');
            Route::get('/users/{user}/photo', [UserController::class, 'downloadPhoto'])->name('api.v1.user_management.users.photo.download');
            Route::get('/users/{user}/activity', [UserController::class, 'activity'])->name('api.v1.user_management.users.activity');

            Route::get('/roles', [RoleController::class, 'index'])->name('api.v1.user_management.roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->name('api.v1.user_management.roles.store');
            Route::get('/roles/{role}', [RoleController::class, 'show'])->name('api.v1.user_management.roles.show');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('api.v1.user_management.roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('api.v1.user_management.roles.destroy');
            Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('api.v1.user_management.roles.permissions');
            Route::get('/permissions', [RoleController::class, 'permissions'])->name('api.v1.user_management.permissions.index');

            Route::get('/organizations', [OrganizationController::class, 'index'])->name('api.v1.user_management.organizations.index');
            Route::post('/organizations', [OrganizationController::class, 'store'])->name('api.v1.user_management.organizations.store');
            Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('api.v1.user_management.organizations.show');
            Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('api.v1.user_management.organizations.update');
            Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('api.v1.user_management.organizations.destroy');
        });

        // 15. Module 13: Notification & Alert Management (notification.*)
        Route::prefix('notifications')->group(function () {
            Route::get('/dashboard', [NotificationDashboardController::class, 'summary'])->name('api.v1.notifications.dashboard');
            Route::get('/lookups', [NotificationDashboardController::class, 'lookups'])->name('api.v1.notifications.lookups');

            // Self-service In-App Inbox, Preferences & Flutter Push Devices
            Route::get('/inbox', [NotificationDashboardController::class, 'inbox'])->name('api.v1.notifications.inbox');
            Route::post('/inbox/{dispatch}/read', [NotificationDashboardController::class, 'markRead'])->whereUuid('dispatch')->name('api.v1.notifications.inbox.read');
            Route::post('/inbox/read-all', [NotificationDashboardController::class, 'markAllRead'])->name('api.v1.notifications.inbox.read_all');

            Route::get('/preferences', [NotificationDashboardController::class, 'preferences'])->name('api.v1.notifications.preferences.index');
            Route::put('/preferences', [NotificationDashboardController::class, 'updatePreferences'])->name('api.v1.notifications.preferences.update');

            Route::get('/devices', [NotificationDashboardController::class, 'devices'])->name('api.v1.notifications.devices.index');
            Route::post('/devices', [NotificationDashboardController::class, 'registerDevice'])->name('api.v1.notifications.devices.store');
            Route::delete('/devices/{device}', [NotificationDashboardController::class, 'deactivateDevice'])->whereUuid('device')->name('api.v1.notifications.devices.destroy');

            // Bilingual Templates
            Route::get('/templates', [NotificationTemplateController::class, 'index'])->name('api.v1.notifications.templates.index');
            Route::post('/templates', [NotificationTemplateController::class, 'store'])->name('api.v1.notifications.templates.store');
            Route::get('/templates/{template}', [NotificationTemplateController::class, 'show'])->whereUuid('template')->name('api.v1.notifications.templates.show');
            Route::put('/templates/{template}', [NotificationTemplateController::class, 'update'])->whereUuid('template')->name('api.v1.notifications.templates.update');
            Route::delete('/templates/{template}', [NotificationTemplateController::class, 'destroy'])->whereUuid('template')->name('api.v1.notifications.templates.destroy');

            // Alert Rules & Active Alerts
            Route::get('/alert-rules', [AlertController::class, 'rules'])->name('api.v1.notifications.alert_rules.index');
            Route::post('/alert-rules', [AlertController::class, 'storeRule'])->name('api.v1.notifications.alert_rules.store');
            Route::put('/alert-rules/{rule}', [AlertController::class, 'updateRule'])->whereUuid('rule')->name('api.v1.notifications.alert_rules.update');
            Route::delete('/alert-rules/{rule}', [AlertController::class, 'destroyRule'])->whereUuid('rule')->name('api.v1.notifications.alert_rules.destroy');

            Route::get('/alerts', [AlertController::class, 'alerts'])->name('api.v1.notifications.alerts.index');
            Route::post('/alerts/trigger', [AlertController::class, 'trigger'])->name('api.v1.notifications.alerts.trigger');
            Route::post('/alerts/escalate', [AlertController::class, 'runEscalations'])->name('api.v1.notifications.alerts.escalate');
            Route::get('/alerts/{alert}', [AlertController::class, 'showAlert'])->whereUuid('alert')->name('api.v1.notifications.alerts.show');
            Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->whereUuid('alert')->name('api.v1.notifications.alerts.acknowledge');
            Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])->whereUuid('alert')->name('api.v1.notifications.alerts.resolve');

            // Notification Campaigns & Delivery Logs
            Route::get('/dispatches', [NotificationDispatchController::class, 'dispatches'])->name('api.v1.notifications.dispatches.index');
            Route::post('/dispatches/{dispatch}/retry', [NotificationDispatchController::class, 'retryDispatch'])->whereUuid('dispatch')->name('api.v1.notifications.dispatches.retry');
            Route::post('/process-scheduled', [NotificationDispatchController::class, 'processScheduled'])->name('api.v1.notifications.process_scheduled');

            Route::get('/', [NotificationDispatchController::class, 'index'])->name('api.v1.notifications.index');
            Route::post('/', [NotificationDispatchController::class, 'store'])->name('api.v1.notifications.store');
            Route::get('/{notification}', [NotificationDispatchController::class, 'show'])->whereUuid('notification')->name('api.v1.notifications.show');
        });

        // 16. Module 14: Document Management (document.*)
        Route::prefix('documents')->group(function () {
            Route::get('/dashboard', [DocumentDashboardController::class, 'summary'])->name('api.v1.documents.dashboard');
            Route::get('/lookups', [DocumentDashboardController::class, 'lookups'])->name('api.v1.documents.lookups');
            Route::get('/target-entities', [DocumentDashboardController::class, 'targetEntities'])->name('api.v1.documents.target_entities');

            Route::get('/categories', [DocumentCategoryController::class, 'index'])->name('api.v1.documents.categories.index');
            Route::post('/categories', [DocumentCategoryController::class, 'store'])->name('api.v1.documents.categories.store');
            Route::put('/categories/{category}', [DocumentCategoryController::class, 'update'])->whereUuid('category')->name('api.v1.documents.categories.update');
            Route::delete('/categories/{category}', [DocumentCategoryController::class, 'destroy'])->whereUuid('category')->name('api.v1.documents.categories.destroy');

            Route::get('/entity/{entityType}/{entityId}', [DocumentController::class, 'entityDocuments'])
                ->whereUuid('entityId')
                ->name('api.v1.documents.entity');

            Route::get('/', [DocumentController::class, 'index'])->name('api.v1.documents.index');
            Route::post('/', [DocumentController::class, 'store'])->name('api.v1.documents.store');
            Route::get('/{document}', [DocumentController::class, 'show'])->whereUuid('document')->name('api.v1.documents.show');
            Route::put('/{document}', [DocumentController::class, 'update'])->whereUuid('document')->name('api.v1.documents.update');
            Route::delete('/{document}', [DocumentController::class, 'destroy'])->whereUuid('document')->name('api.v1.documents.destroy');

            Route::post('/{document}/versions', [DocumentController::class, 'uploadVersion'])->whereUuid('document')->name('api.v1.documents.versions.store');
            Route::post('/{document}/versions/{version}/restore', [DocumentController::class, 'restoreVersion'])->whereUuid(['document', 'version'])->name('api.v1.documents.versions.restore');
            Route::get('/{document}/versions/{version}/download', [DocumentController::class, 'downloadVersion'])->whereUuid(['document', 'version'])->name('api.v1.documents.versions.download');

            Route::get('/{document}/download', [DocumentController::class, 'download'])->whereUuid('document')->name('api.v1.documents.download');
            Route::get('/{document}/preview', [DocumentController::class, 'preview'])->whereUuid('document')->name('api.v1.documents.preview');
            Route::post('/{document}/signed-url', [DocumentController::class, 'signedUrl'])->whereUuid('document')->name('api.v1.documents.signed_url');
            Route::get('/{document}/stream', [DocumentController::class, 'stream'])->whereUuid('document')->name('api.v1.documents.stream');

            Route::post('/{document}/submit-approval', [DocumentController::class, 'submitApproval'])->whereUuid('document')->name('api.v1.documents.submit_approval');
            Route::post('/{document}/approve', [DocumentController::class, 'approve'])->whereUuid('document')->name('api.v1.documents.approve');
            Route::post('/{document}/reject', [DocumentController::class, 'reject'])->whereUuid('document')->name('api.v1.documents.reject');
            Route::post('/{document}/archive', [DocumentController::class, 'archive'])->whereUuid('document')->name('api.v1.documents.archive');
            Route::post('/{document}/verify-integrity', [DocumentController::class, 'verifyIntegrity'])->whereUuid('document')->name('api.v1.documents.verify_integrity');
            Route::get('/{document}/history', [DocumentController::class, 'history'])->whereUuid('document')->name('api.v1.documents.history');
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
