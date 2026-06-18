<?php

declare(strict_types=1);

namespace App\Shared\Rules;

enum Operator: string
{
    case EQUALS = 'equals';
    case NOT_EQUALS = 'not_equals';
    case GREATER_THAN = 'greater_than';
    case LESS_THAN = 'less_than';
    case GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    case LESS_THAN_OR_EQUAL = 'less_than_or_equal';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';
    case CONTAINS = 'contains';
    case CONTAINS_ANY = 'contains_any';

    /**
     * Apply the operator to a value and comparison value.
     * This implementation is left for Task 0.2.
     * For now, this serves as a placeholder for the comparison logic.
     */
    public function apply(mixed $value, mixed $comparisonValue): bool
    {
        // Placeholder - implemented in Task 0.2
        return true;
    }
}
