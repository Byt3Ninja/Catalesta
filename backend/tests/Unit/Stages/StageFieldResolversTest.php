<?php

declare(strict_types=1);

namespace Tests\Unit\Stages;

use App\Shared\Rules\FieldResolverRegistry;
use Tests\TestCase;

/**
 * Verifies that the StagesServiceProvider registers the Phase-2 field resolvers
 * as a singleton and that the registry correctly knows/resolves fields.
 */
final class StageFieldResolversTest extends TestCase
{
    public function test_registry_singleton_is_shared_across_resolutions(): void
    {
        $registry1 = $this->app->make(FieldResolverRegistry::class);
        $registry2 = $this->app->make(FieldResolverRegistry::class);

        $this->assertSame($registry1, $registry2, 'FieldResolverRegistry must be a singleton');
    }

    public function test_registry_knows_cohort_is_open_field(): void
    {
        /** @var FieldResolverRegistry $registry */
        $registry = $this->app->make(FieldResolverRegistry::class);

        $this->assertTrue($registry->knows('cohort.is_open'), 'Registry should know cohort.is_open');
    }

    public function test_registry_knows_participant_current_stage_status_field(): void
    {
        /** @var FieldResolverRegistry $registry */
        $registry = $this->app->make(FieldResolverRegistry::class);

        $this->assertTrue($registry->knows('participant.current_stage_status'), 'Registry should know participant.current_stage_status');
    }

    public function test_registry_knows_context_namespace(): void
    {
        /** @var FieldResolverRegistry $registry */
        $registry = $this->app->make(FieldResolverRegistry::class);

        $this->assertContains('context', $registry->namespaces(), 'context namespace should be registered');
    }

    public function test_registry_does_not_know_unknown_namespace(): void
    {
        /** @var FieldResolverRegistry $registry */
        $registry = $this->app->make(FieldResolverRegistry::class);

        $this->assertFalse($registry->knows('system.exec'), 'system.exec must not be known');
        $this->assertNotContains('system', $registry->namespaces(), 'system namespace must not exist');
    }

    public function test_cohort_resolver_resolves_value_from_context(): void
    {
        /** @var FieldResolverRegistry $registry */
        $registry = $this->app->make(FieldResolverRegistry::class);

        $context = ['cohort.is_open' => true];
        $result = $registry->resolve('cohort.is_open', $context);

        $this->assertTrue($result, 'cohort.is_open should resolve to true from context');
    }

    public function test_context_resolver_resolves_arbitrary_key_from_context(): void
    {
        /** @var FieldResolverRegistry $registry */
        $registry = $this->app->make(FieldResolverRegistry::class);

        $context = ['context.score' => 42];
        $result = $registry->resolve('context.score', $context);

        $this->assertSame(42, $result, 'context.score should resolve to 42 from context');
    }
}
