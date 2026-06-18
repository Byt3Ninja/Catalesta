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
use App\Modules\Programs\Policies\ProgramPolicy;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(OrganizationMembership::class, MembershipPolicy::class);
        Gate::policy(Program::class, ProgramPolicy::class);
        Gate::policy(Cohort::class, CohortPolicy::class);
    }
}
