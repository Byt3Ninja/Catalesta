<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Domain\Models\LinkedIdentity;
use App\Modules\Organizations\Application\CreateOrganization;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Shared\Support\CorrelationId;
use App\Shared\Tenancy\TenantContext;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CorrelationId::reset();
    }

    /**
     * Create an Account with an attached startup_gate LinkedIdentity.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeAccount(array $overrides = []): Account
    {
        $account = Account::create(array_merge([
            'email' => Str::uuid()->toString().'@test.example',
            'is_platform_admin' => false,
            'email_verified_at' => now(),
        ], $overrides));

        LinkedIdentity::create([
            'account_id' => $account->id,
            'provider' => 'startup_gate',
            'subject_id' => 'sub-'.Str::uuid()->toString(),
            'linked_at' => now(),
        ]);

        return $account;
    }

    /**
     * Create a user, act as them (web guard), create an org via the CreateOrganization
     * service (so they become owner with all 4 permissions), seed the permission catalog
     * if it has not been seeded yet, and return [$user, $organization].
     *
     * CreateOrganization requires NO resolved tenant (it sets organization_id explicitly).
     * We temporarily swap in a fresh TenantContext so any prior HTTP-resolved tenant does
     * not bleed into the creating hook and force the wrong organization_id.
     *
     * @return array{0: Account, 1: Organization}
     */
    protected function bootUserWithOrg(string $name = 'Acme'): array
    {
        $this->seed(PermissionCatalogSeeder::class);

        $user = $this->makeAccount();
        $this->actingAs($user, 'web');

        /** @var CreateOrganization $service */
        $service = $this->app->make(CreateOrganization::class);

        $organization = $this->withoutTenantContext(fn () => $service->handle($user, $name));

        return [$user, $organization];
    }

    /**
     * Create an organization owned by a freshly-created user (not the test's primary user).
     * Returns the organization only; the owner user is not returned because it is irrelevant
     * to isolation tests.
     */
    protected function createBareOrg(string $name = 'Other'): Organization
    {
        $this->seed(PermissionCatalogSeeder::class);

        $owner = $this->makeAccount();

        /** @var CreateOrganization $service */
        $service = $this->app->make(CreateOrganization::class);

        return $this->withoutTenantContext(fn () => $service->handle($owner, $name));
    }

    /**
     * Set TenantContext to $org on behalf of $user (resolves their membership).
     * Mirrors the pattern used in feature tests that need a resolved-tenant context
     * without going through HTTP middleware.
     */
    protected function actingAsTenant(
        Account $user,
        Organization $org,
    ): void {
        $this->actingAs($user, 'web');

        $membership = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $org->id)
            ->where('account_id', $user->id)
            ->firstOrFail();

        /** @var TenantContext $ctx */
        $ctx = $this->app->make(TenantContext::class);
        $ctx->setOrganization(
            $org->id,
            $membership,
            $membership->effectivePermissionKeys(),
        );
    }

    /**
     * Discard the currently-resolved TenantContext singleton so that the next
     * HTTP request lets ResolveTenant middleware re-resolve it from scratch.
     * Use this before an HTTP call that follows a direct actingAsTenant() setup
     * (which would otherwise leave a stale tenant in the container).
     */
    protected function resetTenantContext(): void
    {
        $this->app->forgetInstance(TenantContext::class);
    }

    /**
     * Convenience wrapper: reset tenant context, authenticate as $user, and
     * set the X-Organization-Id header — all in one call.
     *
     * This must be used instead of inline actingAs()->withHeader() when model
     * fixtures were created via actingAsTenant() to avoid a stale tenant
     * singleton causing BelongsToTenant global-scope assertions to pass for
     * the wrong reason.
     */
    protected function actingAsTenantRequest(Account $user, Organization $org): static
    {
        $this->resetTenantContext();
        $this->actingAs($user, 'web');

        return $this->withHeader('X-Organization-Id', $org->id);
    }

    /**
     * Execute $fn with a completely clean TenantContext (no org, not system).
     * Restores the previous TenantContext instance afterwards so that tests
     * that resolve a tenant via HTTP middleware keep their resolved context.
     *
     * This is the safe way to call CreateOrganization (and similar bootstrap
     * operations) even when a prior HTTP request has already resolved a tenant.
     *
     * @template T
     *
     * @param  callable():T  $fn
     * @return T
     */
    protected function withoutTenantContext(callable $fn): mixed
    {
        // Stash the current resolved instance (may be null if not yet resolved)
        $previous = $this->app->resolved(TenantContext::class)
            ? $this->app->make(TenantContext::class)
            : null;

        // Replace with a brand-new clean context
        $this->app->forgetInstance(TenantContext::class);
        $this->app->instance(TenantContext::class, new TenantContext);

        try {
            return $fn();
        } finally {
            // Restore the previous context (or forget if there was none)
            $this->app->forgetInstance(TenantContext::class);
            if ($previous !== null) {
                $this->app->instance(TenantContext::class, $previous);
            }
        }
    }
}
