<?php

declare(strict_types=1);

namespace Tests\Unit\Forms;

use App\Modules\Forms\Domain\Exceptions\InvalidFormDefinitionException;
use App\Modules\Forms\Domain\FormDefinitionValidator;
use Tests\TestCase;

final class FormDefinitionValidatorTest extends TestCase
{
    public function test_rejects_unknown_field_type(): void
    {
        $validator = new FormDefinitionValidator;
        $definition = [
            ['type' => 'unknown_type', 'label' => 'Name', 'id' => 'a'],
        ];

        $this->expectException(InvalidFormDefinitionException::class);
        $this->expectExceptionMessage('Unsupported field type: unknown_type');
        $validator->validate($definition);
    }

    public function test_rejects_forbidden_code_key_expr(): void
    {
        $validator = new FormDefinitionValidator;
        $definition = [
            ['type' => 'number', 'label' => 'Score', 'expr' => 'evil()'],
        ];

        $this->expectException(InvalidFormDefinitionException::class);
        $this->expectExceptionMessage("Form definitions are declarative; key 'expr' is not allowed");
        $validator->validate($definition);
    }

    public function test_rejects_forbidden_code_key_in_nested_structure(): void
    {
        $validator = new FormDefinitionValidator;
        $definition = [
            ['type' => 'short_text', 'label' => 'Name', 'validation' => ['rule' => 'custom_logic']],
        ];

        $this->expectException(InvalidFormDefinitionException::class);
        $this->expectExceptionMessage("Form definitions are declarative; key 'rule' is not allowed");
        $validator->validate($definition);
    }

    public function test_accepts_valid_definition(): void
    {
        $validator = new FormDefinitionValidator;
        $definition = [
            ['type' => 'short_text', 'label' => 'Name', 'id' => 'f1', 'required' => true],
            ['type' => 'number', 'label' => 'Score', 'id' => 'f2'],
        ];

        // Should not throw
        $validator->validate($definition);
        $this->assertTrue(true);
    }

    public function test_rejects_empty_definition(): void
    {
        $validator = new FormDefinitionValidator;
        $definition = [];

        $this->expectException(InvalidFormDefinitionException::class);
        $this->expectExceptionMessage('A form must define at least one field');
        $validator->validate($definition);
    }
}
