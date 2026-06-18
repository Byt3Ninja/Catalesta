<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

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
     * Apply the operator to a resolved field value and a comparison value.
     *
     * Non-numeric operands passed to numeric comparison operators (greater_than,
     * less_than, greater_than_or_equal, less_than_or_equal) return false rather
     * than throwing — a non-numeric value cannot satisfy a numeric comparison.
     */
    public function apply(mixed $value, mixed $comparisonValue): bool
    {
        return match ($this) {
            self::GREATER_THAN => ($c = self::decimalCompare($value, $comparisonValue)) !== null && $c > 0,
            self::GREATER_THAN_OR_EQUAL => ($c = self::decimalCompare($value, $comparisonValue)) !== null && $c >= 0,
            self::LESS_THAN => ($c = self::decimalCompare($value, $comparisonValue)) !== null && $c < 0,
            self::LESS_THAN_OR_EQUAL => ($c = self::decimalCompare($value, $comparisonValue)) !== null && $c <= 0,
            self::EQUALS => self::scalarEquals($value, $comparisonValue),
            self::NOT_EQUALS => ! self::scalarEquals($value, $comparisonValue),
            self::IN => is_array($comparisonValue) && in_array($value, $comparisonValue, strict: false),
            self::NOT_IN => ! is_array($comparisonValue) || ! in_array($value, $comparisonValue, strict: false),
            self::CONTAINS => self::containsCheck($value, $comparisonValue),
            self::CONTAINS_ANY => self::containsAnyCheck($value, $comparisonValue),
            self::IS_NULL => $value === null,
            self::IS_NOT_NULL => $value !== null,
        };
    }

    /**
     * Decimal-safe comparison. Returns -1, 0, or 1.
     * Returns null when either operand is not a valid numeric string/int/float.
     *
     * Callers treat a null return as false (condition not satisfied).
     */
    private static function decimalCompare(mixed $left, mixed $right): ?int
    {
        try {
            $result = BigDecimal::of((string) $left)->compareTo(BigDecimal::of((string) $right));

            // compareTo() returns a negative int, zero, or a positive int
            if ($result < 0) {
                return -1;
            }
            if ($result > 0) {
                return 1;
            }

            return 0;
        } catch (MathException) {
            // Non-numeric operand — return null so numeric comparisons evaluate to false
            return null;
        }
    }

    /**
     * Equality check: for numeric scalars compare via BigDecimal so that
     * '0.30' == '0.3'; for non-numeric scalars use loose == comparison.
     */
    private static function scalarEquals(mixed $left, mixed $right): bool
    {
        if (is_scalar($left) && is_scalar($right)) {
            try {
                return BigDecimal::of((string) $left)->compareTo(BigDecimal::of((string) $right)) === 0;
            } catch (MathException) {
                // Not numeric — fall through to loose scalar comparison
            }
        }

        return $left == $right;
    }

    /**
     * Contains check:
     *   - array left  → in_array($right, $left)
     *   - string left → str_contains($left, (string) $right)
     */
    private static function containsCheck(mixed $left, mixed $right): bool
    {
        if (is_array($left)) {
            return in_array($right, $left, strict: false);
        }

        if (is_string($left)) {
            return str_contains($left, (string) $right);
        }

        return false;
    }

    /**
     * Contains-any check: both operands must be arrays; returns true when their
     * intersection is non-empty.
     */
    private static function containsAnyCheck(mixed $left, mixed $right): bool
    {
        if (! is_array($left) || ! is_array($right)) {
            return false;
        }

        return count(array_intersect($left, $right)) > 0;
    }
}
