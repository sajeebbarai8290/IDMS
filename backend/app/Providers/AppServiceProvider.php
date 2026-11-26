<?php

namespace App\Providers;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Policies\HouseholdPolicy;
use App\Modules\Disaster\Adapters\MockDisasterNotificationAdapter;
use App\Modules\Disaster\Contracts\DisasterNotificationInterface;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Disaster\Policies\DisasterPolicy;
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
use App\Modules\Response\Models\ResponseActivityType;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseSituationReport;
use App\Modules\Response\Policies\ResponsePolicy;
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
    }
}
