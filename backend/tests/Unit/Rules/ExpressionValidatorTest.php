<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Shared\Rules\Exceptions\InvalidExpressionException;
use App\Shared\Rules\ExpressionValidator;
use App\Shared\Rules\FieldResolver;
use App\Shared\Rules\FieldResolverRegistry;
use Tests\TestCase;

final class ExpressionValidatorTest extends TestCase
{
    private function validator(): ExpressionValidator
    {
        $registry = new FieldResolverRegistry;
        $registry->register(new class implements FieldResolver
        {
            public function supports(string $field): bool
            {
                return str_starts_with($field, 'cohort.');
            }

            public function resolve(string $field, array $context): mixed
            {
                return $context[$field] ?? null;
            }

            public function namespaces(): array
            {
                return ['cohort'];
            }
        });

        return new ExpressionValidator($registry);
    }

    public function test_accepts_a_valid_tree(): void
    {
        $this->validator()->validate([
            'all' => [
                ['field' => 'cohort.is_open', 'operator' => 'equals', 'value' => true],
            ],
        ]);
        $this->addToAssertionCount(1); // no exception
    }

    public function test_rejects_unknown_operator(): void
    {
        $this->expectException(InvalidExpressionException::class);
        $this->validator()->validate(['all' => [['field' => 'cohort.x', 'operator' => 'eval', 'value' => 1]]]);
    }

    public function test_rejects_unknown_field_namespace(): void
    {
        $this->expectException(InvalidExpressionException::class);
        $this->validator()->validate(['all' => [['field' => 'system.exec', 'operator' => 'equals', 'value' => 1]]]);
    }

    public function test_rejects_non_structured_node(): void
    {
        $this->expectException(InvalidExpressionException::class);
        $this->validator()->validate(['php' => 'system("rm -rf /")']);
    }
}
