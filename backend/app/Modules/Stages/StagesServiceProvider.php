<?php

declare(strict_types=1);

namespace App\Modules\Stages;

use App\Modules\Stages\Infrastructure\Rules\CohortFieldResolver;
use App\Modules\Stages\Infrastructure\Rules\ContextFieldResolver;
use App\Modules\Stages\Infrastructure\Rules\ParticipantFieldResolver;
use App\Shared\Rules\ExpressionEvaluator;
use App\Shared\Rules\ExpressionValidator;
use App\Shared\Rules\FieldResolverRegistry;
use Illuminate\Support\ServiceProvider;

class StagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind FieldResolverRegistry as a singleton so all consumers
        // (ExpressionValidator, ExpressionEvaluator, model saving hooks)
        // share the same instance and see the registered Phase-2 resolvers.
        $this->app->singleton(FieldResolverRegistry::class, function (): FieldResolverRegistry {
            $registry = new FieldResolverRegistry;

            $registry->register(new ParticipantFieldResolver);
            $registry->register(new CohortFieldResolver);
            $registry->register(new ContextFieldResolver);

            return $registry;
        });

        // Bind ExpressionValidator using the singleton registry so validation
        // in model saving hooks (app(ExpressionValidator::class)) sees the resolvers.
        $this->app->bind(ExpressionValidator::class, function (): ExpressionValidator {
            return new ExpressionValidator(
                $this->app->make(FieldResolverRegistry::class),
            );
        });

        // Bind ExpressionEvaluator using the singleton registry.
        $this->app->bind(ExpressionEvaluator::class, function (): ExpressionEvaluator {
            return new ExpressionEvaluator(
                $this->app->make(FieldResolverRegistry::class),
            );
        });
    }
}
