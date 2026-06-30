<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Application\PublishStagePipeline;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StagePipelineReadTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PublishStagePipeline
    {
        return $this->app->make(PublishStagePipeline::class);
    }

    /**
     * A program with two published stages (Screening → FE 'task', Review → FE 'review').
     * Sets up tenant context and publishes the pipeline, returning key objects.
     *
     * @return array{0: Account, 1: Organization, 2: Program, 3: StagePipelineVersion}
     */
    private function publishedPipelineFixture(): array
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $program = Program::create(['name' => 'Accelerator', 'organization_id' => $org->id]);

        // Stage 1: Screening → FE type 'task' (not in TYPE_MAP default branch)
        $stage1 = ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'screen', 'name' => 'Screening', 'type' => StageType::Screening, 'order_index' => 0,
        ]);
        $sv1 = StageVersion::create([
            'program_stage_id' => $stage1->id, 'organization_id' => $org->id,
            'status' => 'published', 'version_number' => 1, 'config' => [], 'published_at' => now(),
        ]);
        $stage1->update(['current_published_version_id' => $sv1->id]);

        // Stage 2: Review → FE type 'review' (explicit entry in TYPE_MAP)
        $stage2 = ProgramStage::create([
            'program_id' => $program->id, 'organization_id' => $org->id,
            'key' => 'review', 'name' => 'Review Panel', 'type' => StageType::Review, 'order_index' => 1,
        ]);
        $sv2 = StageVersion::create([
            'program_stage_id' => $stage2->id, 'organization_id' => $org->id,
            'status' => 'published', 'version_number' => 1, 'config' => [], 'published_at' => now(),
        ]);
        $stage2->update(['current_published_version_id' => $sv2->id]);

        $pipelineVersion = $this->service()->handle($program->refresh());

        return [$user, $org, $program, $pipelineVersion];
    }

    // ── Publish endpoint ──────────────────────────────────────────────────────

    public function test_publish_returns_200_with_version_resource(): void
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
            'status' => 'published', 'version_number' => 1, 'config' => [], 'published_at' => now(),
        ]);
        $stage->update(['current_published_version_id' => $v->id]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/programs/{$program->id}/stage-pipelines/publish");

        $res->assertStatus(200)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonStructure(['data' => [
                'version_id', 'pipeline_id', 'version', 'status', 'stages', 'created_at', 'published_at',
            ]]);
    }

    public function test_publish_422_when_program_has_no_stages(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Empty', 'organization_id' => $org->id]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/programs/{$program->id}/stage-pipelines/publish");

        $res->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['stages']]);
    }

    public function test_publish_cross_tenant_returns_404(): void
    {
        [, , $program] = $this->publishedPipelineFixture();
        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->postJson("/api/v1/programs/{$program->id}/stage-pipelines/publish")
            ->assertStatus(404);
    }

    public function test_publish_without_stages_manage_returns_403(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Accelerator', 'organization_id' => $org->id]);

        // A member with no role assignment has no permission keys (no stages.manage).
        // Mirror the pattern from CohortOpenBindTest::test_bind_requires_cohorts_manage_403.
        $member = $this->makeAccount();
        $memberMembership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        // resetTenantContext() so ResolveTenant middleware re-resolves from the header.
        $this->resetTenantContext();

        $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stage-pipelines/publish")
            ->assertStatus(403);
    }

    // ── Index endpoint ────────────────────────────────────────────────────────

    public function test_index_returns_program_pipeline(): void
    {
        [$user, $org, $program] = $this->publishedPipelineFixture();

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/programs/{$program->id}/stage-pipelines");

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.program_id', $program->id)
            ->assertJsonStructure(['data' => [['pipeline_id', 'program_id', 'name', 'latest_version', 'published_version_ids', 'current_draft_version_id', 'created_at']]]);
    }

    // ── Pipeline show endpoint ────────────────────────────────────────────────

    public function test_show_returns_pipeline_with_correct_fields(): void
    {
        [$user, $org, $program, $pipelineVersion] = $this->publishedPipelineFixture();
        $pipelineId = $pipelineVersion->stage_pipeline_id;

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/stage-pipelines/{$pipelineId}");

        $res->assertStatus(200)
            ->assertJsonPath('data.pipeline_id', $pipelineId)
            ->assertJsonPath('data.program_id', $program->id)
            ->assertJsonPath('data.latest_version', 1)
            ->assertJsonPath('data.current_draft_version_id', null)
            ->assertJsonStructure(['data' => ['pipeline_id', 'program_id', 'name', 'latest_version', 'published_version_ids', 'current_draft_version_id', 'created_at']]);
    }

    public function test_show_cross_tenant_returns_404(): void
    {
        [, , , $pipelineVersion] = $this->publishedPipelineFixture();
        $pipelineId = $pipelineVersion->stage_pipeline_id;
        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/stage-pipelines/{$pipelineId}")
            ->assertStatus(404);
    }

    // ── Versions list endpoint ────────────────────────────────────────────────

    public function test_versions_returns_pipeline_versions(): void
    {
        [$user, $org, , $pipelineVersion] = $this->publishedPipelineFixture();
        $pipelineId = $pipelineVersion->stage_pipeline_id;

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/stage-pipelines/{$pipelineId}/versions");

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.version_id', $pipelineVersion->id)
            ->assertJsonPath('data.0.version', 1)
            ->assertJsonPath('data.0.status', 'published');
    }

    public function test_versions_cross_tenant_returns_404(): void
    {
        [, , , $pipelineVersion] = $this->publishedPipelineFixture();
        $pipelineId = $pipelineVersion->stage_pipeline_id;
        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/stage-pipelines/{$pipelineId}/versions")
            ->assertStatus(404);
    }

    // ── Version show endpoint + FE shape ─────────────────────────────────────

    public function test_version_show_returns_fe_shaped_stages(): void
    {
        [$user, $org, , $pipelineVersion] = $this->publishedPipelineFixture();

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/stage-pipeline-versions/{$pipelineVersion->id}");

        $res->assertStatus(200)
            ->assertJsonPath('data.version_id', $pipelineVersion->id)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonCount(2, 'data.stages')
            // Stage 0: Screening → FE type 'task' (backend 'screening' not in TYPE_MAP → default)
            ->assertJsonPath('data.stages.0.type', 'task')
            ->assertJsonPath('data.stages.0.order', 0)
            ->assertJsonPath('data.stages.0.entry_rule', null)
            ->assertJsonPath('data.stages.0.exit_rule', null)
            ->assertJsonPath('data.stages.0.parallel_group', null)
            ->assertJsonStructure(['data' => ['stages' => [['stage_id', 'name', 'type', 'order', 'entry_rule', 'exit_rule', 'next_stage_ids', 'depends_on_stage_ids', 'parallel_group']]]])
            // Stage 1: Review → FE type 'review' (explicit TYPE_MAP entry)
            ->assertJsonPath('data.stages.1.type', 'review')
            ->assertJsonPath('data.stages.1.order', 1);
    }

    public function test_version_show_cross_tenant_returns_404(): void
    {
        [, , , $pipelineVersion] = $this->publishedPipelineFixture();
        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/stage-pipeline-versions/{$pipelineVersion->id}")
            ->assertStatus(404);
    }
}
