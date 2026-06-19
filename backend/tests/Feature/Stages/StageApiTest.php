<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageVersion;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Stage API (Task 3.3).
 *
 * Coverage:
 *   - Owner POST /api/v1/programs/{program}/stages → 201 (stage + draft v1)
 *   - POST second stage → 201 + order_index = 1
 *   - POST /api/v1/programs/{program}/stages/reorder → 200 + order_index updated
 *   - POST /api/v1/stages/{id}/publish → 200 + current_published_version_id set
 *   - PATCH published stage version config → 422/409 (immutable — not silently mutated)
 *   - Two stages with same parallel_group + a StageTransition between them → persisted
 *   - Member WITHOUT stages.manage → 403
 *   - Cross-tenant (stage/program of Org B, header Org A) → 404/403 (no leak)
 *   - Reorder with foreign stage id (not in program) → 422
 */
final class StageApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy-path: create a stage
    // -------------------------------------------------------------------------

    public function test_owner_can_create_stage_returns_201_with_draft_version(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $program = new Program(['name' => 'Test Program', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'application',
                'name' => 'Application Stage',
                'type' => 'application',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.key', 'application')
            ->assertJsonPath('data.name', 'Application Stage')
            ->assertJsonPath('data.type', 'application')
            ->assertJsonPath('data.order_index', 0)
            ->assertJsonStructure(['data' => ['id', 'program_id', 'key', 'name', 'type', 'order_index', 'parallel_group', 'current_published_version_id', 'versions']]);

        $stageId = $response->json('data.id');

        $this->assertDatabaseHas('program_stages', [
            'id' => $stageId,
            'program_id' => $program->id,
            'organization_id' => $org->id,
            'key' => 'application',
        ]);

        // A draft version must have been auto-created
        $this->assertDatabaseHas('stage_versions', [
            'program_stage_id' => $stageId,
            'status' => 'draft',
            'version_number' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // Happy-path: second stage gets next order_index
    // -------------------------------------------------------------------------

    public function test_second_stage_gets_incremented_order_index(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $program = new Program(['name' => 'Test Program 2', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'stage-one',
                'name' => 'Stage One',
                'type' => 'screening',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.order_index', 0);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'stage-two',
                'name' => 'Stage Two',
                'type' => 'interview',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.order_index', 1);
    }

    // -------------------------------------------------------------------------
    // Reorder
    // -------------------------------------------------------------------------

    public function test_owner_can_reorder_stages(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $program = new Program(['name' => 'Reorder Program', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        $r1 = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'alpha',
                'name' => 'Alpha',
                'type' => 'application',
            ]);
        $r2 = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'beta',
                'name' => 'Beta',
                'type' => 'screening',
            ]);

        $stageAId = $r1->json('data.id');
        $stageBId = $r2->json('data.id');

        // Reverse order: Beta first, Alpha second
        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/reorder", [
                'stage_ids' => [$stageBId, $stageAId],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('program_stages', ['id' => $stageBId, 'order_index' => 0]);
        $this->assertDatabaseHas('program_stages', ['id' => $stageAId, 'order_index' => 1]);
    }

    // -------------------------------------------------------------------------
    // Publish
    // -------------------------------------------------------------------------

    public function test_owner_can_publish_stage_version(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $program = new Program(['name' => 'Publish Program', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        $createResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'publish-stage',
                'name' => 'Publish Stage',
                'type' => 'evaluation',
            ]);

        $stageId = $createResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/stages/{$stageId}/publish");

        $response->assertStatus(200);

        // current_published_version_id must be set on the stage
        $stage = ProgramStage::find($stageId);
        $this->assertNotNull($stage->current_published_version_id);

        // Version must have status=published
        $this->assertDatabaseHas('stage_versions', [
            'program_stage_id' => $stageId,
            'status' => 'published',
        ]);
    }

    // -------------------------------------------------------------------------
    // PATCH on a published version → 422/409 (not silently mutated)
    // -------------------------------------------------------------------------

    public function test_patch_published_version_config_is_rejected_with_422_or_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $program = new Program(['name' => 'Immutable Program', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        $createResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'immutable-stage',
                'name' => 'Immutable Stage',
                'type' => 'review',
            ]);

        $stageId = $createResponse->json('data.id');

        // Publish the stage first
        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/stages/{$stageId}/publish")
            ->assertStatus(200);

        // Now try to PATCH the config of the published version
        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->patchJson("/api/v1/stages/{$stageId}", [
                'config' => ['max_applicants' => 100],
            ]);

        // Must be rejected — NOT 200
        $this->assertContains($response->status(), [422, 409]);

        // Config must NOT have been changed
        $version = StageVersion::where('program_stage_id', $stageId)->first();
        $this->assertNotEquals(['max_applicants' => 100], $version->config);
    }

    // -------------------------------------------------------------------------
    // parallel_group + StageTransition persisted
    // -------------------------------------------------------------------------

    public function test_parallel_group_and_transition_are_persisted(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $program = new Program(['name' => 'Parallel Program', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        // Create two stages with the same parallel_group
        $r1 = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'parallel-a',
                'name' => 'Parallel A',
                'type' => 'training',
                'parallel_group' => 'track-1',
            ]);
        $r2 = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'parallel-b',
                'name' => 'Parallel B',
                'type' => 'training',
                'parallel_group' => 'track-1',
            ]);

        $stageAId = $r1->json('data.id');
        $stageBId = $r2->json('data.id');

        // Both stages must have the parallel_group stored
        $this->assertDatabaseHas('program_stages', ['id' => $stageAId, 'parallel_group' => 'track-1']);
        $this->assertDatabaseHas('program_stages', ['id' => $stageBId, 'parallel_group' => 'track-1']);

        // Create a transition between them directly (represents conditional parallel flow)
        // organization_id is set via direct assignment (not mass-assignable)
        $transition = new StageTransition([
            'program_id' => $program->id,
            'from_program_stage_id' => $stageAId,
            'to_program_stage_id' => $stageBId,
            'condition' => null,
        ]);
        $transition->organization_id = $org->id;
        $transition->save();

        $this->assertDatabaseHas('stage_transitions', [
            'id' => $transition->id,
            'from_program_stage_id' => $stageAId,
            'to_program_stage_id' => $stageBId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Authorization: member without stages.manage → 403
    // -------------------------------------------------------------------------

    public function test_member_without_stages_manage_cannot_create_stage(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('No-Perm Org');

        $program = new Program(['name' => 'Restricted Program', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        $member = $this->makeExternalUser();
        $memberMembership = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'forbidden',
                'name' => 'Forbidden Stage',
                'type' => 'screening',
            ]);

        $response->assertStatus(403);
    }

    public function test_member_without_stages_manage_cannot_publish_stage(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        [, $org] = $this->bootUserWithOrg('Publish Perm Org');

        $program = new Program(['name' => 'Publish Test Program', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        // Create stage as owner — direct assignment for organization_id
        $stage = new ProgramStage([
            'program_id' => $program->id,
            'key' => 'to-publish',
            'name' => 'To Publish',
            'type' => 'evaluation',
            'order_index' => 0,
        ]);
        $stage->organization_id = $org->id;
        $stage->save();

        $stageVersion = new StageVersion([
            'program_stage_id' => $stage->id,
            'status' => 'draft',
            'version_number' => 0,
        ]);
        $stageVersion->organization_id = $org->id;
        $stageVersion->save();

        $member = $this->makeExternalUser();
        $memberMembership2 = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $memberMembership2->organization_id = $org->id;
        $memberMembership2->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/stages/{$stage->id}/publish");

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: Org B stage accessed with Org A header → 404/403
    // -------------------------------------------------------------------------

    public function test_cross_tenant_stage_access_is_blocked(): void
    {
        // Org B creates a stage
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B');

        $programB = new Program(['name' => 'Org B Program', 'status' => 'draft']);
        $programB->organization_id = $orgB->id;
        $programB->save();

        $createResponse = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/stages", [
                'key' => 'secret-stage',
                'name' => 'Secret Stage',
                'type' => 'screening',
            ])
            ->assertStatus(201);

        $stageBId = $createResponse->json('data.id');

        // Org A user tries to publish Org B's stage
        [$userA, $orgA] = $this->bootUserWithOrg('Org A');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/stages/{$stageBId}/publish");

        $this->assertContains($response->status(), [403, 404]);

        // Org B's stage must still be unpublished
        $this->assertDatabaseHas('program_stages', [
            'id' => $stageBId,
            'current_published_version_id' => null,
        ]);
    }

    public function test_cross_tenant_reorder_with_foreign_stage_id_returns_422(): void
    {
        // Org A creates their own program + stage via the API
        [$userA, $orgA] = $this->bootUserWithOrg('Reorder Org A');

        $programAResponse = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson('/api/v1/programs', ['name' => 'Org A Reorder Program'])
            ->assertStatus(201);

        $programAId = $programAResponse->json('data.id');

        $stageAResponse = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programAId}/stages", [
                'key' => 'local-stage',
                'name' => 'Local Stage',
                'type' => 'custom',
            ])
            ->assertStatus(201);

        $localStageId = $stageAResponse->json('data.id');

        // Create a "foreign" stage directly in the DB (belongs to a different org/program).
        // We bypass the API here to avoid the multi-bootUserWithOrg auth session collision.
        $orgB = $this->createBareOrg('Reorder Org B');

        $programB = Program::withoutGlobalScope('tenant')->create([
            'name' => 'Org B Reorder Program',
            'status' => 'draft',
            'organization_id' => $orgB->id,
        ]);

        $foreignStage = ProgramStage::withoutGlobalScope('tenant')->create([
            'organization_id' => $orgB->id,
            'program_id' => $programB->id,
            'key' => 'foreign-stage',
            'name' => 'Foreign Stage',
            'type' => 'custom',
            'order_index' => 0,
        ]);

        $foreignStageId = $foreignStage->id;

        // Org A tries to reorder their program with the foreign (Org B) stage id mixed in → 422
        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programAId}/stages/reorder", [
                'stage_ids' => [$localStageId, $foreignStageId],
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Index: list stages ordered by order_index
    // -------------------------------------------------------------------------

    public function test_owner_can_list_stages_for_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $program = new Program(['name' => 'List Stages Program', 'status' => 'draft']);
        $program->organization_id = $org->id;
        $program->save();

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages", [
                'key' => 'list-stage',
                'name' => 'List Stage',
                'type' => 'application',
            ])
            ->assertStatus(201);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/programs/{$program->id}/stages");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'key', 'name', 'type', 'order_index']]]);

        $keys = collect($response->json('data'))->pluck('key')->toArray();
        $this->assertContains('list-stage', $keys);
    }
}
