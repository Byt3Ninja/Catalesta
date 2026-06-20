<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Policies\CohortPolicy;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Policies\MembershipPolicy;
use App\Modules\Organizations\Policies\OrganizationPolicy;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\Track;
use App\Modules\Programs\Policies\ProgramPolicy;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Policies\StagePolicy;
use App\Shared\Entitlement\AllowAllEntitlementService;
use App\Shared\Entitlement\EntitlementService;
use App\Shared\Outbox\Consumers\LogOutboxConsumer;
use App\Shared\Outbox\Contracts\OutboxConsumer;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);

        // The single P1a outbox consumer (log transport). Multi-consumer is P2.
        $this->app->bind(OutboxConsumer::class, LogOutboxConsumer::class);

        // Entitlement seam (FR-060): allow-all socket in P1a; real counter in P1b.
        $this->app->bind(EntitlementService::class, AllowAllEntitlementService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(OrganizationMembership::class, MembershipPolicy::class);
        Gate::policy(Program::class, ProgramPolicy::class);
        Gate::policy(Track::class, ProgramPolicy::class);
        Gate::policy(Cohort::class, CohortPolicy::class);
        Gate::policy(ProgramStage::class, StagePolicy::class);
    }
}
