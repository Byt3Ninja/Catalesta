<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageRuleType;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Rules\Exceptions\InvalidExpressionException;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validates that StageRule and StageTransition reject invalid expressions on save
 * and accept valid ones, using the singleton FieldResolverRegistry with Phase-2 resolvers.
 */
final class StageRuleValidationTest extends TestCase
{
    use RefreshDatabase;

    private function setTenantContext(): string
    {
        [$user, $org] = $this->bootUserWithOrg();

        $membership = OrganizationMembership::query()
            ->where('organization_id', $org->id)
            ->where('external_user_id', $user->id)
            ->firstOrFail();

        $this->app->make(TenantContext::class)
            ->setOrganization($org->id, $membership, $membership->effectivePermissionKeys());

        return $org->id;
    }

    private function makeStageVersion(string $orgId): StageVersion
    {
        $program = Program::create(['name' => 'Test Program']);

        $stage = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'screening',
            'name' => 'Screening',
            'type' => StageType::Screening,
            'order_index' => 1,
        ]);

        return StageVersion::create([
            'program_stage_id' => $stage->id,
            'config' => [],
        ]);
    }

    // -------------------------------------------------------------------------
    // StageRule: valid expression persists
    // -------------------------------------------------------------------------

    public function test_stage_rule_with_valid_expression_persists(): void
    {
        $this->setTenantContext();
        $version = $this->makeStageVersion('');

        $rule = StageRule::create([
            'stage_version_id' => $version->id,
            'type' => StageRuleType::Entry,
            'expression' => [
                'field' => 'cohort.is_open',
                'operator' => 'equals',
                'value' => true,
            ],
        ]);

        $this->assertNotNull($rule->id);
        $this->assertSame(26, strlen($rule->id));
        $this->assertSame(StageRuleType::Entry, $rule->type);
        $this->assertSame('cohort.is_open', $rule->expression['field']);
    }

    public function test_stage_rule_with_exit_type_and_participant_field_persists(): void
    {
        $this->setTenantContext();
        $version = $this->makeStageVersion('');

        $rule = StageRule::create([
            'stage_version_id' => $version->id,
            'type' => StageRuleType::Exit,
            'expression' => [
                'field' => 'participant.current_stage_status',
                'operator' => 'equals',
                'value' => 'completed',
            ],
        ]);

        $this->assertNotNull($rule->id);
        $this->assertSame(StageRuleType::Exit, $rule->type);
    }

    public function test_stage_rule_with_context_namespace_persists(): void
    {
        $this->setTenantContext();
        $version = $this->makeStageVersion('');

        $rule = StageRule::create([
            'stage_version_id' => $version->id,
            'type' => StageRuleType::Entry,
            'expression' => [
                'field' => 'context.score',
                'operator' => 'greater_than',
                'value' => 50,
            ],
        ]);

        $this->assertNotNull($rule->id);
    }

    // -------------------------------------------------------------------------
    // StageRule: invalid expression throws
    // -------------------------------------------------------------------------

    public function test_stage_rule_with_unknown_field_namespace_throws(): void
    {
        $this->setTenantContext();
        $version = $this->makeStageVersion('');

        $this->expectException(InvalidExpressionException::class);

        StageRule::create([
            'stage_version_id' => $version->id,
            'type' => StageRuleType::Entry,
            'expression' => [
                'field' => 'system.exec',
                'operator' => 'equals',
                'value' => true,
            ],
        ]);
    }

    public function test_stage_rule_with_unknown_operator_throws(): void
    {
        $this->setTenantContext();
        $version = $this->makeStageVersion('');

        $this->expectException(InvalidExpressionException::class);

        StageRule::create([
            'stage_version_id' => $version->id,
            'type' => StageRuleType::Entry,
            'expression' => [
                'field' => 'cohort.is_open',
                'operator' => 'execute_shell',
                'value' => true,
            ],
        ]);
    }

    public function test_stage_rule_with_empty_expression_does_not_validate(): void
    {
        // Empty array should skip validation (guard: only validate when non-empty array)
        $this->setTenantContext();
        $version = $this->makeStageVersion('');

        // No exception — empty expression skips validation
        $rule = StageRule::create([
            'stage_version_id' => $version->id,
            'type' => StageRuleType::Entry,
            'expression' => [],
        ]);

        $this->assertNotNull($rule->id);
    }

    // -------------------------------------------------------------------------
    // StageTransition: valid condition persists
    // -------------------------------------------------------------------------

    public function test_stage_transition_with_valid_condition_persists(): void
    {
        $this->setTenantContext();
        $program = Program::create(['name' => 'Transition Program']);

        $stage1 = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'intro',
            'name' => 'Intro',
            'type' => StageType::Screening,
            'order_index' => 1,
        ]);

        $stage2 = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'main',
            'name' => 'Main',
            'type' => StageType::Interview,
            'order_index' => 2,
        ]);

        $transition = StageTransition::create([
            'program_id' => $program->id,
            'from_program_stage_id' => $stage1->id,
            'to_program_stage_id' => $stage2->id,
            'condition' => [
                'field' => 'cohort.is_open',
                'operator' => 'equals',
                'value' => true,
            ],
            'order_index' => 0,
        ]);

        $this->assertNotNull($transition->id);
        $this->assertSame(26, strlen($transition->id));
    }

    public function test_stage_transition_without_condition_persists(): void
    {
        $this->setTenantContext();
        $program = Program::create(['name' => 'Unconditional Program']);

        $stage = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'only',
            'name' => 'Only Stage',
            'type' => StageType::Screening,
            'order_index' => 1,
        ]);

        $transition = StageTransition::create([
            'program_id' => $program->id,
            'from_program_stage_id' => null,
            'to_program_stage_id' => $stage->id,
            'order_index' => 0,
        ]);

        $this->assertNotNull($transition->id);
        $this->assertNull($transition->condition);
    }

    // -------------------------------------------------------------------------
    // StageTransition: invalid condition throws
    // -------------------------------------------------------------------------

    public function test_stage_transition_with_unknown_field_namespace_throws(): void
    {
        $this->setTenantContext();
        $program = Program::create(['name' => 'Bad Transition Program']);

        $stage = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'only',
            'name' => 'Only Stage',
            'type' => StageType::Screening,
            'order_index' => 1,
        ]);

        $this->expectException(InvalidExpressionException::class);

        StageTransition::create([
            'program_id' => $program->id,
            'from_program_stage_id' => null,
            'to_program_stage_id' => $stage->id,
            'condition' => [
                'field' => 'system.exec',
                'operator' => 'equals',
                'value' => true,
            ],
            'order_index' => 0,
        ]);
    }
}
