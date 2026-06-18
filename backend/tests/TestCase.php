<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Application\CreateOrganization;
use App\Modules\Organizations\Domain\Models\Organization;
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
     * Create an ExternalUser with a random subject ID.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function makeExternalUser(array $overrides = []): ExternalUser
    {
        return ExternalUser::create(array_merge([
            'startup_gate_subject_id' => 'sub-'.Str::uuid()->toString(),
            'email' => Str::uuid()->toString().'@test.example',
            'is_platform_admin' => false,
        ], $overrides));
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
     * @return array{0: ExternalUser, 1: Organization}
     */
    protected function bootUserWithOrg(string $name = 'Acme'): array
    {
        $this->seed(PermissionCatalogSeeder::class);

        $user = $this->makeExternalUser();
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

        $owner = $this->makeExternalUser();

        /** @var CreateOrganization $service */
        $service = $this->app->make(CreateOrganization::class);

        return $this->withoutTenantContext(fn () => $service->handle($owner, $name));
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
