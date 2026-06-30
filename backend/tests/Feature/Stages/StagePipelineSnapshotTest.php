<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Application\PublishStagePipeline;
use App\Modules\Stages\Domain\Exceptions\StagePipelineNotPublishableException;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageDependency;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageRuleType;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Audit\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StagePipelineSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PublishStagePipeline
    {
        return $this->app->make(PublishStagePipeline::class);
    }

    /** A program with one fully-published stage, under tenant context. */
    private function programWithPublishedStage(): Program
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Accelerator', 'organization_id' => $org->id]);
        $stage = ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'screen', 'name' => 'Screening', 'type' => StageType::Screening, 'order_index' => 0,
        ]);
        $v = StageVersion::create([
            'program_stage_id' => $stage->id, 'organization_id' => $org->id,
            'status' => 'published', 'version_number' => 1, 'config' => ['k' => 'v'], 'published_at' => now(),
        ]);
        $stage->update(['current_published_version_id' => $v->id]);

        return $program->refresh();
    }

    public function test_publishes_an_immutable_content_addressed_snapshot(): void
    {
        $program = $this->programWithPublishedStage();

        $version = $this->service()->handle($program);

        $this->assertSame('published', $version->status->value);
        $this->assertSame(1, $version->version_number);
        $this->assertSame(64, strlen((string) $version->content_hash));
        $this->assertCount(1, $version->snapshot['stages']);
        $this->assertSame('screen', $version->snapshot['stages'][0]['key']);
        $this->assertArrayHasKey('stage_id', $version->snapshot['stages'][0]);

        // Fix 3: each snapshot node must pin stage_version_id = published StageVersion id
        $stageNode = $version->snapshot['stages'][0];
        /** @var ProgramStage $ps */
        $ps = ProgramStage::query()->findOrFail($stageNode['stage_id']);
        $this->assertSame($ps->current_published_version_id, $stageNode['stage_version_id']);
    }

    public function test_republish_of_identical_graph_is_idempotent(): void
    {
        $program = $this->programWithPublishedStage();
        $a = $this->service()->handle($program);
        $b = $this->service()->handle($program->refresh());

        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('stage_pipeline_versions', 1);
    }

    public function test_422_when_a_stage_is_unpublished(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Accelerator', 'organization_id' => $org->id]);
        ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'screen', 'name' => 'Screening', 'type' => StageType::Screening, 'order_index' => 0,
        ]); // no published version

        $this->expectException(StagePipelineNotPublishableException::class);
        $this->expectExceptionMessageMatches('/screen/');
        $this->service()->handle($program->refresh());
    }

    public function test_422_when_program_has_no_stages(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Empty', 'organization_id' => $org->id]);

        $this->expectException(StagePipelineNotPublishableException::class);
        $this->service()->handle($program->refresh());
    }

    public function test_snapshot_lists_are_order_stable_regardless_of_insert_order(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'OrderStability', 'organization_id' => $org->id]);

        // Create three stages; stageB will depend on both stageA and stageC
        $stageA = ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'stage-a', 'name' => 'Stage A', 'type' => StageType::Screening, 'order_index' => 0,
        ]);
        $stageB = ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'stage-b', 'name' => 'Stage B', 'type' => StageType::Screening, 'order_index' => 1,
        ]);
        $stageC = ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'stage-c', 'name' => 'Stage C', 'type' => StageType::Screening, 'order_index' => 2,
        ]);

        // Publish versions; add two StageRules for stageA in REVERSE type order (exit before entry)
        $vA = StageVersion::create([
            'program_stage_id' => $stageA->id, 'organization_id' => $org->id,
            'status' => 'published', 'version_number' => 1, 'config' => [], 'published_at' => now(),
        ]);
        $stageA->update(['current_published_version_id' => $vA->id]);
        // Insert exit first, entry second — non-deterministic DB return order without ORDER BY
        StageRule::create(['stage_version_id' => $vA->id, 'type' => StageRuleType::Exit, 'expression' => []]);
        StageRule::create(['stage_version_id' => $vA->id, 'type' => StageRuleType::Entry, 'expression' => []]);

        $vB = StageVersion::create([
            'program_stage_id' => $stageB->id, 'organization_id' => $org->id,
            'status' => 'published', 'version_number' => 1, 'config' => [], 'published_at' => now(),
        ]);
        $stageB->update(['current_published_version_id' => $vB->id]);
        // Insert dependencies in REVERSE id order (stageC first so its id appears first in raw DB result)
        StageDependency::create(['program_stage_id' => $stageB->id, 'depends_on_program_stage_id' => $stageC->id]);
        StageDependency::create(['program_stage_id' => $stageB->id, 'depends_on_program_stage_id' => $stageA->id]);

        $vC = StageVersion::create([
            'program_stage_id' => $stageC->id, 'organization_id' => $org->id,
            'status' => 'published', 'version_number' => 1, 'config' => [], 'published_at' => now(),
        ]);
        $stageC->update(['current_published_version_id' => $vC->id]);

        $pipelineVersion = $this->service()->handle($program->refresh());
        $stages = collect($pipelineVersion->snapshot['stages']);

        // Rules for stageA must be sorted by type (entry < exit) regardless of insertion order
        $stageANode = $stages->firstWhere('key', 'stage-a');
        $this->assertNotNull($stageANode);
        $ruleTypes = array_column($stageANode['rules'], 'type');
        $this->assertSame(['entry', 'exit'], $ruleTypes, 'rules must be deterministically ordered by type then id');

        // depends_on_stage_ids for stageB must be sorted (lexicographic ULID order)
        $stageBNode = $stages->firstWhere('key', 'stage-b');
        $this->assertNotNull($stageBNode);
        $deps = $stageBNode['depends_on_stage_ids'];
        $this->assertCount(2, $deps);
        $sorted = collect($deps)->sort()->values()->all();
        $this->assertSame($sorted, $deps, 'depends_on_stage_ids must be deterministically sorted');
    }

    public function test_audit_fires_only_on_new_version_not_idempotent_republish(): void
    {
        $program = $this->programWithPublishedStage();

        // First publish creates a version and fires audit
        $this->service()->handle($program);
        $publishCount = AuditLog::query()->where('action', 'stage_pipeline.published')->count();
        $this->assertSame(1, $publishCount);

        // Idempotent republish returns same version without firing audit
        $this->service()->handle($program->refresh());
        $publishCount = AuditLog::query()->where('action', 'stage_pipeline.published')->count();
        $this->assertSame(1, $publishCount); // Still 1, audit did not fire again
    }
}
