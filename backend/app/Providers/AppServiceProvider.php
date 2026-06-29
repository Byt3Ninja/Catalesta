<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Applications\Policies\ApplicationSubmissionPolicy;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Policies\CohortPolicy;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Policies\FormPolicy;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Policies\MembershipPolicy;
use App\Modules\Organizations\Policies\OrganizationPolicy;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\Track;
use App\Modules\Programs\Policies\ProgramPolicy;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Policies\StagePolicy;
use App\Shared\Audit\AuthorizationAuditRecorder;
use App\Shared\Entitlement\AllowAllEntitlementService;
use App\Shared\Entitlement\EntitlementService;
use App\Shared\Outbox\Consumers\LogOutboxConsumer;
use App\Shared\Outbox\Contracts\OutboxConsumer;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(Form::class, FormPolicy::class);
        Gate::policy(ApplicationSubmission::class, ApplicationSubmissionPolicy::class);

        $perEmailIp = fn (Request $r) => Limit::perMinute(6)
            ->by(strtolower((string) $r->input('email')).'|'.$r->ip());

        RateLimiter::for('auth-register', fn (Request $r) => Limit::perMinute(6)->by((string) $r->ip()));
        RateLimiter::for('auth-login', $perEmailIp);
        RateLimiter::for('auth-forgot', $perEmailIp);
        RateLimiter::for('auth-resend', fn (Request $r) => Limit::perMinute(6)->by(optional($r->user())->id ?: (string) $r->ip()));

        // Scramble's RestrictedDocsAccess always allows `local`; this widens the
        // /docs/api viewer to `staging` too, while production stays 403.
        // ponytail: env gate; require an admin role here if staging docs must be locked down.
        // Nullable user param so the gate runs for unauthenticated docs requests (guests).
        Gate::define('viewApiDocs', fn (?Authenticatable $user) => $this->app->environment('staging'));

        // RA.2: enforced audit of authorization decisions (denials + sensitive allows).
        $this->app->make(AuthorizationAuditRecorder::class)->register();
    }
}
