<?php

declare(strict_types=1);

namespace App\Modules\Stages\Infrastructure\Rules;

use App\Shared\Rules\FieldResolver;

/**
 * Resolves fields in the `cohort` namespace.
 *
 * Known fields:
 *   - cohort.is_open
 *
 * Values are read directly from the evaluation context array by field key.
 */
final class CohortFieldResolver implements FieldResolver
{
    /** @var string[] */
    private const FIELDS = [
        'cohort.is_open',
    ];

    public function supports(string $field): bool
    {
        return in_array($field, self::FIELDS, true);
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
        return ['cohort'];
    }
}
