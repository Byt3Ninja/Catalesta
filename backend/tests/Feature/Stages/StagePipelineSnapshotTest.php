<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Application\PublishStagePipeline;
use App\Modules\Stages\Domain\Exceptions\StagePipelineNotPublishableException;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
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
}
