<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use App\Shared\Rules\Exceptions\UnknownFieldException;

final class FieldResolverRegistry
{
    /**
     * @var FieldResolver[]
     */
    private array $resolvers = [];

    /**
     * Register a field resolver.
     */
    public function register(FieldResolver $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * Check if a field is known by any registered resolver.
     */
    public function knows(string $field): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all known namespaces from registered resolvers.
     *
     * @return string[]
     */
    public function namespaces(): array
    {
        $namespaces = [];
        foreach ($this->resolvers as $resolver) {
            $namespaces = array_merge($namespaces, $resolver->namespaces());
        }

        return array_unique($namespaces);
    }

    /**
     * Resolve a field using the first matching resolver.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws UnknownFieldException if no resolver supports the field
     */
    public function resolve(string $field, array $context): mixed
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($field)) {
                return $resolver->resolve($field, $context);
            }
        }

        throw new UnknownFieldException("No resolver found for field: {$field}");
    }
}
