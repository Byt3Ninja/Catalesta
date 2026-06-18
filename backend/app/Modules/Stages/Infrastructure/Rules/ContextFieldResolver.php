<?php

declare(strict_types=1);

namespace App\Modules\Stages\Infrastructure\Rules;

use App\Shared\Rules\FieldResolver;

/**
 * Resolves fields in the `context` namespace.
 *
 * The context resolver supports any field prefixed with `context.`, allowing
 * arbitrary runtime context keys to be referenced in expressions.
 *
 * Values are read directly from the evaluation context array by field key.
 */
final class ContextFieldResolver implements FieldResolver
{
    public function supports(string $field): bool
    {
        return str_starts_with($field, 'context.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $field, array $context): mixed
    {
        return $context[$field] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function namespaces(): array
    {
        return ['context'];
    }
}
