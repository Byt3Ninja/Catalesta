<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use App\Shared\Rules\Exceptions\UnknownFieldException;

final class ExpressionEvaluator
{
    public function __construct(
        private readonly FieldResolverRegistry $registry,
    ) {}

    /**
     * Evaluate an expression tree against a context.
     *
     * Supported node shapes:
     *   - Group (AND): ['all' => [...children]]
     *   - Group (OR):  ['any' => [...children]]
     *   - Leaf:        ['field' => '...', 'operator' => '...', 'value' => mixed]
     *
     * @param  array<string, mixed>  $tree
     * @param  array<string, mixed>  $context
     */
    public function evaluate(array $tree, array $context): bool
    {
        if (isset($tree['all'])) {
            /** @var array<int, array<string, mixed>> $children */
            $children = $tree['all'];
            foreach ($children as $child) {
                if (! $this->evaluate($child, $context)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($tree['any'])) {
            /** @var array<int, array<string, mixed>> $children */
            $children = $tree['any'];
            foreach ($children as $child) {
                if ($this->evaluate($child, $context)) {
                    return true;
                }
            }

            return false;
        }

        // Leaf node
        /** @var string $field */
        $field = $tree['field'] ?? '';
        /** @var string $op */
        $op = $tree['operator'] ?? '';
        $value = $tree['value'] ?? null;

        try {
            $resolved = $this->registry->resolve($field, $context);
        } catch (UnknownFieldException) {
            return false;
        }

        $operator = Operator::tryFrom($op);
        if ($operator === null) {
            return false;
        }

        return $operator->apply($resolved, $value);
    }
}
