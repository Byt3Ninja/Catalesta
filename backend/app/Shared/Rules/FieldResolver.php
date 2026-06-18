<?php

declare(strict_types=1);

namespace App\Shared\Rules;

interface FieldResolver
{
    /**
     * Determine if this resolver supports the given field.
     */
    public function supports(string $field): bool;

    /**
     * Resolve the given field using the provided context.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $field, array $context): mixed;

    /**
     * Return the list of namespaces this resolver handles.
     * For example, a cohort resolver might return ['cohort'].
     *
     * @return array<int, string>
     */
    public function namespaces(): array;
}
