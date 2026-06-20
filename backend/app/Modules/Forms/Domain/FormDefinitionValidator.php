<?php

declare(strict_types=1);

namespace App\Modules\Forms\Domain;

use App\Modules\Forms\Domain\Exceptions\InvalidFormDefinitionException;

/**
 * Validates that a form definition is declarative data only (NFR-005): a list of
 * fields, each with an allowed FieldType and plain props — no code/expression
 * nodes anywhere in the structure.
 */
final class FormDefinitionValidator
{
    /** Keys that would smuggle executable logic into a "declarative" definition. */
    private const FORBIDDEN_KEYS = ['expr', 'expression', 'code', 'formula', 'script', 'eval', 'fn', 'rule'];

    /**
     * @param  array<int, mixed>  $definition  list of (untrusted) field definitions
     *
     * @throws InvalidFormDefinitionException
     */
    public function validate(array $definition): void
    {
        if ($definition === []) {
            throw new InvalidFormDefinitionException('A form must define at least one field.');
        }

        foreach ($definition as $field) {
            if (! is_array($field) || ! isset($field['type']) || ! is_string($field['type'])) {
                throw new InvalidFormDefinitionException('Each field must be an object with a string "type".');
            }
            if (FieldType::tryFrom($field['type']) === null) {
                throw new InvalidFormDefinitionException("Unsupported field type: {$field['type']}.");
            }
            $this->assertNoCode($field);
        }
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function assertNoCode(array $node): void
    {
        foreach ($node as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                throw new InvalidFormDefinitionException("Form definitions are declarative; key '{$key}' is not allowed.");
            }
            if (is_array($value)) {
                $this->assertNoCode($value);
            }
        }
    }

    /**
     * Stable canonical serialization: recursively key-sorted JSON (AC-5).
     *
     * @param  array<array-key, mixed>  $definition
     */
    public function canonicalJson(array $definition): string
    {
        $sorted = $this->ksortRecursive($definition);

        return json_encode($sorted, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function ksortRecursive(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->ksortRecursive($v);
            }
        }

        return $value;
    }
}
