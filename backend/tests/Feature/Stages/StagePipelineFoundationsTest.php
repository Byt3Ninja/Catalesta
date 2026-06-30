<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Stages\Domain\Models\StagePipeline;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StagePipelineFoundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_actions_exist(): void
    {
        $this->assertSame('stage_pipeline.published', AuditAction::StagePipelinePublished->value);
        $this->assertSame('cohort.stage_pipeline_bound', AuditAction::CohortStagePipelineBound->value);
    }

    public function test_pipeline_and_version_persist_and_freeze(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $pipeline = StagePipeline::create(['program_id' => (string) Str::ulid(), 'name' => 'Default']);
        $version = StagePipelineVersion::create([
            'stage_pipeline_id' => $pipeline->id,
            'status' => 'published',
            'version_number' => 1,
            'content_hash' => str_repeat('a', 64),
            'snapshot' => ['stages' => []],
            'published_at' => now(),
        ]);

        $this->assertSame($pipeline->id, $version->stage_pipeline_id);
        $this->assertSame(['stages' => []], $version->snapshot);

        $this->expectException(VersionStateException::class);
        $version->update(['snapshot' => ['stages' => [['x' => 1]]]]); // immutable once published
    }

    public function test_a_draft_version_persists_without_content_hash(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $pipeline = StagePipeline::create(['program_id' => (string) Str::ulid(), 'name' => 'Default']);
        $draft = StagePipelineVersion::create(['stage_pipeline_id' => $pipeline->id, 'snapshot' => ['stages' => []]]);

        $this->assertSame('draft', $draft->status->value);
        $this->assertSame(0, $draft->version_number);
        $this->assertNull($draft->content_hash);
    }
}
