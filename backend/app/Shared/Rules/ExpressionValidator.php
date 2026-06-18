<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use App\Shared\Rules\Exceptions\InvalidExpressionException;

final class ExpressionValidator
{
    public function __construct(
        private readonly FieldResolverRegistry $registry,
    ) {}

    /**
     * Validate an expression tree recursively.
     *
     * A valid expression is one of:
     * - ['all' => [...]] - all conditions must be true
     * - ['any' => [...]] - any condition must be true
     * - A leaf node with 'field', 'operator', and optionally 'value'
     *
     * @throws InvalidExpressionException if the expression is invalid
     */
    public function validate(mixed $expression): void
    {
        // Expression must be an array
        if (! is_array($expression)) {
            throw new InvalidExpressionException('Expression must be an array');
        }

        // Check if it's a composite node (all/any)
        if (isset($expression['all'])) {
            if (! is_array($expression['all'])) {
                throw new InvalidExpressionException('The "all" key must contain an array');
            }
            foreach ($expression['all'] as $child) {
                $this->validate($child);
            }

            return;
        }

        if (isset($expression['any'])) {
            if (! is_array($expression['any'])) {
                throw new InvalidExpressionException('The "any" key must contain an array');
            }
            foreach ($expression['any'] as $child) {
                $this->validate($child);
            }

            return;
        }

        // If it's not all/any, it must be a leaf node
        $this->validateLeaf($expression);
    }

    /**
     * Validate a leaf expression node.
     *
     * A leaf node must have:
     * - 'field' (string)
     * - 'operator' (valid Operator)
     * - 'value' (optional for is_null/is_not_null)
     *
     * @throws InvalidExpressionException if the leaf is invalid
     */
    private function validateLeaf(mixed $node): void
    {
        // Leaf must be an array with exactly 'field' and 'operator' keys (and optionally 'value')
        if (! is_array($node)) {
            throw new InvalidExpressionException('Leaf node must be an array');
        }

        // Must have 'field' key
        if (! isset($node['field']) || ! is_string($node['field'])) {
            throw new InvalidExpressionException('Leaf node must have a "field" (string)');
        }

        // Must have 'operator' key
        if (! isset($node['operator']) || ! is_string($node['operator'])) {
            throw new InvalidExpressionException('Leaf node must have an "operator" (string)');
        }

        $field = $node['field'];
        $operator = $node['operator'];

        // Validate operator
        $operatorEnum = Operator::tryFrom($operator);
        if ($operatorEnum === null) {
            throw new InvalidExpressionException("Unknown operator: {$operator}");
        }

        // Validate field
        $this->validateField($field);

        // Validate value presence for operators that require it
        $nullOperators = [Operator::IS_NULL, Operator::IS_NOT_NULL];
        $requiresValue = ! in_array($operatorEnum, $nullOperators, true);

        if ($requiresValue && ! array_key_exists('value', $node)) {
            throw new InvalidExpressionException("Operator {$operator} requires a 'value' key");
        }

        if (! $requiresValue && array_key_exists('value', $node) === false) {
            // is_null and is_not_null don't require a value, and it's ok if they don't have one
            // but it's also ok if they do
        }
    }

    /**
     * Validate that a field is known to the registry.
     *
     * A field is valid if:
     * - The registry knows it directly, OR
     * - Its namespace (substring before first '.') is in the registry's namespaces
     *
     * @throws InvalidExpressionException if the field is unknown
     */
    private function validateField(string $field): void
    {
        // Check if registry knows this field directly
        if ($this->registry->knows($field)) {
            return;
        }

        // Extract namespace (substring before first '.')
        $parts = explode('.', $field);
        $namespace = $parts[0];

        if ($namespace === '') {
            throw new InvalidExpressionException("Unknown field: {$field}");
        }

        // Check if the namespace is in the registry's known namespaces
        $knownNamespaces = $this->registry->namespaces();
        if (! in_array($namespace, $knownNamespaces, true)) {
            throw new InvalidExpressionException("Unknown field namespace: {$namespace}");
        }
    }
}
