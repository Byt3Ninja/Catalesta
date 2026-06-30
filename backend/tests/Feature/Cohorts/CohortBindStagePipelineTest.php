<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Stages\Domain\Models\StagePipeline;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CohortBindStagePipelineTest extends TestCase
{
    use RefreshDatabase;

    /** A published StagePipelineVersion in the current tenant. */
    private function publishedVersion(?string $hash = null): StagePipelineVersion
    {
        $pipeline = StagePipeline::create(['program_id' => (string) Str::ulid(), 'name' => 'Pipeline']);

        return StagePipelineVersion::create([
            'stage_pipeline_id' => $pipeline->id,
            'status' => 'published',
            'version_number' => 1,
            'content_hash' => $hash ?? str_repeat('a', 64),
            'snapshot' => [],
            'published_at' => now(),
        ]);
    }

    private function draftCohort(): Cohort
    {
        return Cohort::create(['program_id' => (string) Str::ulid(), 'name' => 'Spring', 'status' => 'draft']);
    }

    public function test_bind_stage_pipeline_sets_the_version_and_returns_200(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", ['stage_pipeline_version_id' => $version->id]);

        $res->assertStatus(200)->assertJsonPath('data.stage_pipeline_version_id', $version->id);
    }

    public function test_bind_same_version_is_idempotent(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['stage_pipeline_version_id' => $version->id]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", ['stage_pipeline_version_id' => $version->id])
            ->assertStatus(200);
    }

    public function test_bind_different_version_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $v1 = $this->publishedVersion(str_repeat('a', 64));
        $cohort->update(['stage_pipeline_version_id' => $v1->id]);
        $v2 = $this->publishedVersion(str_repeat('b', 64));

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", ['stage_pipeline_version_id' => $v2->id])
            ->assertStatus(409);
    }

    public function test_bind_on_non_draft_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['stage_pipeline_version_id' => $version->id, 'status' => 'open']);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", ['stage_pipeline_version_id' => $version->id])
            ->assertStatus(409);
    }

    public function test_bind_missing_cohort_returns_404(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $version = $this->publishedVersion();

        $this->actingAsTenantRequest($user, $org)
            ->postJson('/api/v1/cohorts/'.(string) Str::ulid().'/bind-stage-pipeline', ['stage_pipeline_version_id' => $version->id])
            ->assertStatus(404);
    }

    public function test_bind_unknown_or_draft_version_is_404(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        // a draft (unpublished) version is not bindable
        $pipeline = StagePipeline::create(['program_id' => (string) Str::ulid(), 'name' => 'Pipeline']);
        $draftVersion = StagePipelineVersion::create([
            'stage_pipeline_id' => $pipeline->id,
            'snapshot' => [],
        ]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", ['stage_pipeline_version_id' => $draftVersion->id])
            ->assertStatus(404);
    }

    public function test_bind_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", ['stage_pipeline_version_id' => $version->id])
            ->assertStatus(404);
    }

    public function test_bind_rejects_foreign_tenant_version_with_404(): void
    {
        // Create a draft cohort in org A.
        [$userA, $orgA] = $this->bootUserWithOrg();
        $this->actingAsTenant($userA, $orgA);
        $cohort = $this->draftCohort();

        // Switch to org B and create a published StagePipelineVersion there.
        [$userB, $orgB] = $this->bootUserWithOrg('Org B');
        $this->resetTenantContext();
        $this->actingAsTenant($userB, $orgB);
        $orgBVersion = $this->publishedVersion(str_repeat('b', 64));

        // Acting as org A, the org-B version is invisible via BelongsToTenant →
        // findOrFail throws → 404.
        $this->resetTenantContext();
        $this->actingAsTenantRequest($userA, $orgA)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", ['stage_pipeline_version_id' => $orgBVersion->id])
            ->assertStatus(404);
    }

    public function test_bind_requires_cohorts_manage_403(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        // Create a member of $org with NO permission keys (no cohorts.manage).
        $member = $this->makeAccount();
        $memberMembership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        // resetTenantContext() clears the stale singleton so ResolveTenant
        // middleware re-resolves it from the X-Organization-Id header.
        $this->resetTenantContext();

        $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", ['stage_pipeline_version_id' => $version->id])
            ->assertStatus(403);
    }

    public function test_bind_returns_422_when_version_id_is_absent(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();

        // Empty body — BindCohortStagePipelineRequest requires stage_pipeline_version_id.
        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-stage-pipeline", [])
            ->assertStatus(422);
    }
}
