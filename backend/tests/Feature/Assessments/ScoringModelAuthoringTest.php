<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScoringModelAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private function criteria(): array
    {
        return [
            ['criterion_id' => 'c1', 'label' => 'Innovation', 'max_points' => 30, 'descriptors' => null],
            ['criterion_id' => 'c2', 'label' => 'Market Fit', 'max_points' => 20, 'descriptors' => ['Strong', 'Weak']],
        ];
    }

    // ── list + create (program-nested) ────────────────────────────────

    public function test_list_scoring_models_returns_200_for_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/programs/{$program->id}/scoring-models");

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Eval', $res->json('data.0.name'));
        $this->assertArrayHasKey('model_id', $res->json('data.0'));
        $this->assertArrayHasKey('program_id', $res->json('data.0'));
    }

    public function test_create_scoring_model_returns_201_with_empty_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        // Program::factory() requires a resolved tenant (BelongsToTenant).
        // actingAsTenantRequest resets + re-resolves from X-Organization-Id header.
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/programs/{$program->id}/scoring-models", ['name' => 'Evaluation']);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Evaluation')
            ->assertJsonPath('data.latest_version', 0)
            ->assertJsonPath('data.published_version_ids', []);

        $this->assertNotNull($res->json('data.current_draft_version_id'));
        $this->assertSame('Evaluation', $res->json('data.name'));
        $modelId = $res->json('data.model_id');
        $this->assertDatabaseHas('scoring_models', ['id' => $modelId]);
        $this->assertDatabaseHas('scoring_model_versions', [
            'scoring_model_id' => $modelId,
            'status' => 'draft',
            'version_number' => 0,
        ]);
    }

    public function test_create_requires_name(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/programs/{$program->id}/scoring-models", ['name' => ''])
            ->assertStatus(422);
    }

    public function test_create_requires_authentication(): void
    {
        // Need a tenant to create the Program; then clear both auth + tenant
        // so the HTTP call is truly unauthenticated (bootUserWithOrg calls actingAs).
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $this->resetTenantContext();
        $this->app->make('auth')->forgetGuards(); // clear actingAs state

        $this->postJson("/api/v1/programs/{$program->id}/scoring-models", ['name' => 'X'])
            ->assertStatus(401);
    }

    // ── show ──────────────────────────────────────────────────────────

    public function test_show_returns_200_with_model_id_key(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/scoring-models/{$model->id}");

        $res->assertStatus(200)
            ->assertJsonPath('data.model_id', $model->id)
            ->assertJsonPath('data.program_id', $program->id)
            ->assertJsonPath('data.name', 'Eval');
    }

    public function test_show_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Mine']);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/scoring-models/{$model->id}")
            ->assertStatus(404);
    }

    // ── versions list ─────────────────────────────────────────────────

    public function test_versions_index_lists_by_version_desc(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]); // draft

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/scoring-models/{$model->id}/versions");

        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data'));
        $this->assertSame([1, 0], array_column($res->json('data'), 'version'));
        $this->assertArrayHasKey('version_id', $res->json('data.0'));
        $this->assertArrayHasKey('model_id', $res->json('data.0'));
    }

    // ── version show ──────────────────────────────────────────────────

    public function test_version_show_returns_200_with_version_id_key(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $v = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'criteria' => $this->criteria(),
        ]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->getJson("/api/v1/scoring-model-versions/{$v->id}");

        $res->assertStatus(200)
            ->assertJsonPath('data.version_id', $v->id)
            ->assertJsonPath('data.model_id', $model->id)
            ->assertJsonPath('data.version', 0)
            ->assertJsonPath('data.status', 'draft');

        $this->assertCount(2, $res->json('data.criteria'));
    }

    public function test_version_show_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Mine']);
        $v = ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/scoring-model-versions/{$v->id}")
            ->assertStatus(404);
    }

    // ── saveDraft ─────────────────────────────────────────────────────

    public function test_save_draft_replaces_criteria(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/scoring-models/{$model->id}/draft", ['criteria' => $this->criteria()]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');
        $this->assertCount(2, $res->json('data.criteria'));
        $this->assertSame('Innovation', $res->json('data.criteria.0.label'));
    }

    public function test_save_draft_returns_409_when_no_draft_exists(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        // fully published, no draft
        ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);

        $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/scoring-models/{$model->id}/draft", ['criteria' => $this->criteria()])
            ->assertStatus(409);
    }

    public function test_save_draft_returns_422_for_invalid_criterion(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/scoring-models/{$model->id}/draft", [
                'criteria' => [['criterion_id' => 'c1', 'label' => 'Score', 'max_points' => 0, 'descriptors' => null]],
            ])
            ->assertStatus(422);
    }

    public function test_save_draft_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Mine']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->patchJson("/api/v1/scoring-models/{$model->id}/draft", ['criteria' => []])
            ->assertStatus(404);
    }

    // ── publish ───────────────────────────────────────────────────────

    public function test_publish_promotes_draft_and_returns_200(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'criteria' => $this->criteria(),
        ]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/publish");

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.version', 1);
    }

    public function test_publish_returns_409_when_no_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/publish")
            ->assertStatus(409);
    }

    public function test_publish_returns_422_when_draft_has_no_criteria(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]); // empty

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/publish")
            ->assertStatus(422);
    }

    public function test_publish_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Mine']);
        ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => $this->criteria()]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->postJson("/api/v1/scoring-models/{$model->id}/publish")
            ->assertStatus(404);
    }

    // ── fork ──────────────────────────────────────────────────────────

    public function test_fork_creates_new_draft_from_published_version_returns_201(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $published = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/fork", ['from_version_id' => $published->id]);

        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
        $this->assertCount(2, $res->json('data.criteria'));
        $this->assertSame(2, ScoringModelVersion::where('scoring_model_id', $model->id)->count());
    }

    public function test_fork_with_existing_draft_returns_same_draft_with_200(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $published = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => $this->criteria(), 'published_at' => now(),
        ]);

        $res1 = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/fork", ['from_version_id' => $published->id]);
        $res1->assertStatus(201);
        $draftId = $res1->json('data.version_id');

        $res2 = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/fork", ['from_version_id' => $published->id]);
        $res2->assertStatus(200)->assertJsonPath('data.version_id', $draftId);

        $this->assertSame(1, ScoringModelVersion::where('scoring_model_id', $model->id)->where('status', 'draft')->count());
    }

    public function test_fork_with_unpublished_version_returns_404(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $draft = ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/scoring-models/{$model->id}/fork", ['from_version_id' => $draft->id])
            ->assertStatus(404);
    }

    // ── member without assessments.manage ────────────────────────────

    public function test_member_without_assessments_manage_cannot_create(): void
    {
        [$admin, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($admin, $org);
        $program = Program::factory()->create();

        $member = $this->makeAccount();
        $m = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $m->organization_id = $org->id;
        $m->save();

        $this->resetTenantContext();

        $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/scoring-models", ['name' => 'Blocked'])
            ->assertStatus(403);
    }
}
