<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Application\CreateOrganization;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Shared\Support\CorrelationId;
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
     * @return array{0: ExternalUser, 1: Organization}
     */
    protected function bootUserWithOrg(string $name = 'Acme'): array
    {
        $this->seed(PermissionCatalogSeeder::class);

        $user = $this->makeExternalUser();
        $this->actingAs($user, 'web');

        /** @var CreateOrganization $service */
        $service = $this->app->make(CreateOrganization::class);
        $organization = $service->handle($user, $name);

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

        return $service->handle($owner, $name);
    }
}
