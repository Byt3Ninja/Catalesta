<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Shared\Rules\ExpressionEvaluator;
use App\Shared\Rules\FieldResolver;
use App\Shared\Rules\FieldResolverRegistry;
use Tests\TestCase;

final class ExpressionEvaluatorTest extends TestCase
{
    private function evaluator(): ExpressionEvaluator
    {
        $registry = new FieldResolverRegistry;
        $registry->register(new class implements FieldResolver
        {
            public function supports(string $field): bool
            {
                return str_starts_with($field, 'ctx.');
            }

            public function resolve(string $field, array $context): mixed
            {
                return $context[$field] ?? null;
            }

            public function namespaces(): array
            {
                return ['ctx'];
            }
        });

        return new ExpressionEvaluator($registry);
    }

    public function test_all_and_any_nesting(): void
    {
        $tree = ['all' => [
            ['field' => 'ctx.score', 'operator' => 'greater_than_or_equal', 'value' => 70],
            ['any' => [
                ['field' => 'ctx.role', 'operator' => 'in', 'value' => ['founder', 'mentor']],
                ['field' => 'ctx.flag', 'operator' => 'equals', 'value' => true],
            ]],
        ]];
        $this->assertTrue($this->evaluator()->evaluate($tree, ['ctx.score' => 71, 'ctx.role' => 'mentor', 'ctx.flag' => false]));
        $this->assertFalse($this->evaluator()->evaluate($tree, ['ctx.score' => 69, 'ctx.role' => 'mentor', 'ctx.flag' => false]));
    }

    public function test_decimal_comparison_is_exact(): void
    {
        $tree = ['all' => [['field' => 'ctx.total', 'operator' => 'greater_than', 'value' => '0.1']]];
        // 0.1 + 0.2 = 0.3 exactly under decimal; float would be 0.30000000000000004
        $this->assertTrue($this->evaluator()->evaluate($tree, ['ctx.total' => '0.3']));
    }

    public function test_is_null_and_contains(): void
    {
        $e = $this->evaluator();
        $this->assertTrue($e->evaluate(['all' => [['field' => 'ctx.x', 'operator' => 'is_null']]], ['ctx.x' => null]));
        $this->assertTrue($e->evaluate(['all' => [['field' => 'ctx.tags', 'operator' => 'contains', 'value' => 'a']]], ['ctx.tags' => ['a', 'b']]));
    }
}
