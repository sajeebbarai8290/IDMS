<?php

namespace App\Providers;

use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\UserManagement\Adapters\KeycloakAdminHttpAdapter;
use App\Modules\UserManagement\Adapters\MockKeycloakAdminAdapter;
use App\Modules\UserManagement\Contracts\KeycloakAdminServiceInterface;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use App\Modules\UserManagement\Policies\UserManagementPolicy;
use App\Modules\Beneficiary\Policies\HouseholdPolicy;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use App\Modules\DamageAssessment\Policies\DamageAssessmentPolicy;
use App\Modules\Document\Adapters\ClamAvScannerAdapter;
use App\Modules\Document\Adapters\GovObjectStoreAdapter;
use App\Modules\Document\Adapters\LaravelPrivateStorageAdapter;
use App\Modules\Document\Adapters\MockVirusScannerAdapter;
use App\Modules\Document\Contracts\DocumentStorageInterface;
use App\Modules\Document\Contracts\VirusScannerInterface;
use App\Modules\Document\Models\Document;
use App\Modules\Document\Models\DocumentCategory;
use App\Modules\Document\Policies\DocumentPolicy;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerAttendance;
use App\Modules\Volunteer\Models\VolunteerCategory;
use App\Modules\Volunteer\Models\VolunteerDeployment;
use App\Modules\Volunteer\Models\VolunteerOrganization;
use App\Modules\Volunteer\Models\VolunteerTrainingProgram;
use App\Modules\Volunteer\Models\VolunteerTrainingSession;
use App\Modules\Volunteer\Policies\VolunteerPolicy;
use App\Modules\Disaster\Adapters\MockDisasterNotificationAdapter;
use App\Modules\Disaster\Contracts\DisasterNotificationInterface;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Disaster\Policies\DisasterPolicy;
use App\Modules\Notification\Adapters\FcmPushNotificationAdapter;
use App\Modules\Notification\Adapters\MockPushNotificationAdapter;
use App\Modules\Notification\Adapters\MockSmsGatewayAdapter;
use App\Modules\Notification\Adapters\TeletalkSmsGatewayAdapter;
use App\Modules\Notification\Contracts\PushNotificationInterface;
use App\Modules\Notification\Contracts\SmsGatewayInterface;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Models\AlertRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDispatch;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Policies\NotificationPolicy;
use App\Modules\NidVerification\Adapters\AuthorizedGovernmentNidAdapter;
use App\Modules\NidVerification\Adapters\MockNidAdapter;
use App\Modules\NidVerification\Contracts\NidVerificationServiceInterface;
use App\Modules\NidVerification\Models\NidVerification;
use App\Modules\NidVerification\Policies\NidVerificationPolicy;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectCategory;
use App\Modules\Project\Models\ProjectDisbursement;
use App\Modules\Project\Models\ProjectMilestone;
use App\Modules\Project\Policies\ProjectPolicy;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefCategory;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Relief\Policies\ReliefPolicy;
use App\Modules\Resource\Models\HumanResourceDeployment;
use App\Modules\Resource\Models\ResourceAllocation;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceCategory;
use App\Modules\Resource\Models\ResourceMaintenance;
use App\Modules\Resource\Policies\ResourcePolicy;
use App\Modules\Response\Models\ResponseActivityType;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseSituationReport;
use App\Modules\Response\Policies\ResponsePolicy;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockReceipt;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use App\Modules\Warehouse\Policies\WarehousePolicy;
use App\Modules\Workflow\Adapters\DatabaseWorkflowNotificationAdapter;
use App\Modules\Workflow\Contracts\WorkflowNotificationInterface;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowDelegation;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Models\WorkflowNotification;
use App\Modules\Workflow\Policies\WorkflowPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NidVerificationServiceInterface::class, function ($app) {
            $driver = config('services.nid.driver', 'mock');

            // Hard safety invariant: never allow mock NID adapter in production
            if ($app->environment('production') || $driver === 'government') {
                return $app->make(AuthorizedGovernmentNidAdapter::class);
            }

            return $app->make(MockNidAdapter::class);
        });

        $this->app->bind(
            DisasterNotificationInterface::class,
            MockDisasterNotificationAdapter::class
        );

        $this->app->bind(
            WorkflowNotificationInterface::class,
            DatabaseWorkflowNotificationAdapter::class
        );

        $this->app->bind(KeycloakAdminServiceInterface::class, function ($app) {
            $driver = config('services.keycloak.admin_driver', 'mock');

            // Hard safety invariant: never allow mock Keycloak Admin adapter in production
            if ($app->environment('production') || $driver === 'keycloak') {
                return $app->make(KeycloakAdminHttpAdapter::class);
            }

            return $app->make(MockKeycloakAdminAdapter::class);
        });

        $this->app->bind(SmsGatewayInterface::class, function ($app) {
            $driver = config('services.sms.driver', 'mock');

            // Hard safety invariant: never allow mock SMS adapter in production
            if ($app->environment('production') || $driver === 'teletalk') {
                return $app->make(TeletalkSmsGatewayAdapter::class);
            }

            return $app->make(MockSmsGatewayAdapter::class);
        });

        $this->app->bind(PushNotificationInterface::class, function ($app) {
            $driver = config('services.push.driver', 'mock');

            // Hard safety invariant: never allow mock Push adapter in production
            if ($app->environment('production') || $driver === 'fcm') {
                return $app->make(FcmPushNotificationAdapter::class);
            }

            return $app->make(MockPushNotificationAdapter::class);
        });

        $this->app->bind(DocumentStorageInterface::class, function ($app) {
            $driver = config('services.document.storage_driver', 'laravel_private');

            if ($driver === 'gov_object_store') {
                return $app->make(GovObjectStoreAdapter::class);
            }

            return $app->make(LaravelPrivateStorageAdapter::class);
        });

        $this->app->bind(VirusScannerInterface::class, function ($app) {
            $driver = config('services.document.virus_scanner_driver', 'mock');

            if ($app->environment('production') || $driver === 'clamav') {
                return $app->make(ClamAvScannerAdapter::class);
            }

            return $app->make(MockVirusScannerAdapter::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Household::class, HouseholdPolicy::class);
        Gate::policy(NidVerification::class, NidVerificationPolicy::class);
        Gate::policy(DisasterEvent::class, DisasterPolicy::class);
        Gate::policy(ReliefCategory::class, ReliefPolicy::class);
        Gate::policy(ReliefRequisition::class, ReliefPolicy::class);
        Gate::policy(ReliefAllocation::class, ReliefPolicy::class);
        Gate::policy(DistributionVoucher::class, ReliefPolicy::class);
        Gate::policy(ResponseActivityType::class, ResponsePolicy::class);
        Gate::policy(ResponseOperation::class, ResponsePolicy::class);
        Gate::policy(ResponseSituationReport::class, ResponsePolicy::class);
        Gate::policy(ProjectCategory::class, ProjectPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectMilestone::class, ProjectPolicy::class);
        Gate::policy(ProjectDisbursement::class, ProjectPolicy::class);
        Gate::policy(ResourceCategory::class, ResourcePolicy::class);
        Gate::policy(ResourceAsset::class, ResourcePolicy::class);
        Gate::policy(ResourceAllocation::class, ResourcePolicy::class);
        Gate::policy(ResourceMaintenance::class, ResourcePolicy::class);
        Gate::policy(HumanResourceDeployment::class, ResourcePolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Commodity::class, WarehousePolicy::class);
        Gate::policy(WarehouseInventory::class, WarehousePolicy::class);
        Gate::policy(StockTransaction::class, WarehousePolicy::class);
        Gate::policy(StockReceipt::class, WarehousePolicy::class);
        Gate::policy(WarehouseTransfer::class, WarehousePolicy::class);
        Gate::policy(DamageCategory::class, DamageAssessmentPolicy::class);
        Gate::policy(DamageAssessment::class, DamageAssessmentPolicy::class);
        Gate::policy(VolunteerCategory::class, VolunteerPolicy::class);
        Gate::policy(VolunteerOrganization::class, VolunteerPolicy::class);
        Gate::policy(Volunteer::class, VolunteerPolicy::class);
        Gate::policy(VolunteerDeployment::class, VolunteerPolicy::class);
        Gate::policy(VolunteerAttendance::class, VolunteerPolicy::class);
        Gate::policy(VolunteerTrainingProgram::class, VolunteerPolicy::class);
        Gate::policy(VolunteerTrainingSession::class, VolunteerPolicy::class);
        Gate::policy(WorkflowDefinition::class, WorkflowPolicy::class);
        Gate::policy(WorkflowInstance::class, WorkflowPolicy::class);
        Gate::policy(WorkflowDelegation::class, WorkflowPolicy::class);
        Gate::policy(WorkflowNotification::class, WorkflowPolicy::class);
        Gate::policy(User::class, UserManagementPolicy::class);
        Gate::policy(Role::class, UserManagementPolicy::class);
        Gate::policy(Permission::class, UserManagementPolicy::class);
        Gate::policy(Organization::class, UserManagementPolicy::class);
        Gate::policy(UserAdministrativeScope::class, UserManagementPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(NotificationTemplate::class, NotificationPolicy::class);
        Gate::policy(NotificationDispatch::class, NotificationPolicy::class);
        Gate::policy(AlertRule::class, NotificationPolicy::class);
        Gate::policy(Alert::class, NotificationPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DocumentCategory::class, DocumentPolicy::class);
    }
}
