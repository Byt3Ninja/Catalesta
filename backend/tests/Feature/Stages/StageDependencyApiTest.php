<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Domain\Models\ProgramStage;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Stage Dependency API (Phase 2 Completion, Task 1).
 *
 * Coverage:
 *   - POST /api/v1/programs/{program}/stages/{stage}/dependencies → 201
 *   - GET  /api/v1/programs/{program}/stages/{stage}/dependencies → 200 list
 *   - DELETE /api/v1/stage-dependencies/{id} → 204
 *   - Self-edge (B depends on B) → 422
 *   - Cross-program (B depends on stage of another program) → 422
 *   - Cycle (A→B then B→A) → 422
 *   - Member without stages.manage → 403
 *   - Cross-tenant program/stage ids → 404
 */
final class StageDependencyApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a program and 3 stages (A, B, C) within the given org.
     *
     * @return array{Program, ProgramStage, ProgramStage, ProgramStage}
     */
    private function createProgramWithStages(string $orgId): array
    {
        $program = new Program(['name' => 'Dep Test Program', 'status' => 'draft']);
        $program->organization_id = $orgId;
        $program->save();

        $stageA = new ProgramStage(['program_id' => $program->id, 'key' => 'stage-a', 'name' => 'Stage A', 'type' => 'application', 'order_index' => 0]);
        $stageA->organization_id = $orgId;
        $stageA->save();

        $stageB = new ProgramStage(['program_id' => $program->id, 'key' => 'stage-b', 'name' => 'Stage B', 'type' => 'screening', 'order_index' => 1]);
        $stageB->organization_id = $orgId;
        $stageB->save();

        $stageC = new ProgramStage(['program_id' => $program->id, 'key' => 'stage-c', 'name' => 'Stage C', 'type' => 'evaluation', 'order_index' => 2]);
        $stageC->organization_id = $orgId;
        $stageC->save();

        return [$program, $stageA, $stageB, $stageC];
    }

    // -------------------------------------------------------------------------
    // Happy path: create dependency B depends_on A → 201
    // -------------------------------------------------------------------------

    public function test_owner_can_add_stage_dependency_returns_201(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        [$program, $stageA, $stageB] = $this->createProgramWithStages($org->id);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/{$stageB->id}/dependencies", [
                'depends_on_program_stage_id' => $stageA->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.program_stage_id', $stageB->id)
            ->assertJsonPath('data.depends_on_program_stage_id', $stageA->id);

        $this->assertDatabaseHas('stage_dependencies', [
            'program_stage_id' => $stageB->id,
            'depends_on_program_stage_id' => $stageA->id,
            'organization_id' => $org->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Happy path: list dependencies
    // -------------------------------------------------------------------------

    public function test_owner_can_list_stage_dependencies(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        [$program, $stageA, $stageB] = $this->createProgramWithStages($org->id);

        // Create dependency first
        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/{$stageB->id}/dependencies", [
                'depends_on_program_stage_id' => $stageA->id,
            ])
            ->assertStatus(201);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/programs/{$program->id}/stages/{$stageB->id}/dependencies");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'program_stage_id', 'depends_on_program_stage_id']]])
            ->assertJsonCount(1, 'data');

        $this->assertEquals($stageA->id, $response->json('data.0.depends_on_program_stage_id'));
    }

    // -------------------------------------------------------------------------
    // Happy path: delete dependency → 204
    // -------------------------------------------------------------------------

    public function test_owner_can_delete_stage_dependency(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        [$program, $stageA, $stageB] = $this->createProgramWithStages($org->id);

        $createResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/{$stageB->id}/dependencies", [
                'depends_on_program_stage_id' => $stageA->id,
            ])
            ->assertStatus(201);

        $depId = $createResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->deleteJson("/api/v1/stage-dependencies/{$depId}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('stage_dependencies', ['id' => $depId]);
    }

    // -------------------------------------------------------------------------
    // Validation: self-edge → 422
    // -------------------------------------------------------------------------

    public function test_self_dependency_returns_422(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        [$program, , $stageB] = $this->createProgramWithStages($org->id);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/{$stageB->id}/dependencies", [
                'depends_on_program_stage_id' => $stageB->id, // same as target stage
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_stage_dependency');
    }

    // -------------------------------------------------------------------------
    // Validation: cross-program edge → 422
    // -------------------------------------------------------------------------

    public function test_cross_program_dependency_returns_422(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        [$program, $stageA, $stageB] = $this->createProgramWithStages($org->id);

        // Create a second program with its own stage
        $programX = new Program(['name' => 'Other Program', 'status' => 'draft']);
        $programX->organization_id = $org->id;
        $programX->save();

        $stageX = new ProgramStage(['program_id' => $programX->id, 'key' => 'stage-x', 'name' => 'Stage X', 'type' => 'screening', 'order_index' => 0]);
        $stageX->organization_id = $org->id;
        $stageX->save();

        // B in program 1 depends_on X in program 2 → cross-program → 422
        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/{$stageB->id}/dependencies", [
                'depends_on_program_stage_id' => $stageX->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_stage_dependency');
    }

    // -------------------------------------------------------------------------
    // Validation: cycle → 422 (A depends on B, then B tries to depend on A)
    // -------------------------------------------------------------------------

    public function test_cycle_dependency_returns_422(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        [$program, $stageA, $stageB] = $this->createProgramWithStages($org->id);

        // First: B depends on A (valid)
        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/{$stageB->id}/dependencies", [
                'depends_on_program_stage_id' => $stageA->id,
            ])
            ->assertStatus(201);

        // Now: A tries to depend on B → would create cycle B→A→B → 422
        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/{$stageA->id}/dependencies", [
                'depends_on_program_stage_id' => $stageB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_stage_dependency');
    }

    // -------------------------------------------------------------------------
    // Authorization: member without stages.manage → 403
    // -------------------------------------------------------------------------

    public function test_member_without_stages_manage_cannot_add_dependency(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('No-Perm Org');
        [$program, $stageA, $stageB] = $this->createProgramWithStages($org->id);

        $member = $this->makeExternalUser();
        $membership = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $membership->organization_id = $org->id;
        $membership->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/stages/{$stageB->id}/dependencies", [
                'depends_on_program_stage_id' => $stageA->id,
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: program/stage of Org B with Org A header → 404
    // -------------------------------------------------------------------------

    public function test_cross_tenant_program_returns_404(): void
    {
        // Org B creates a program+stage
        [, $orgB] = $this->bootUserWithOrg('Org B');
        [$programB, $stageAB, $stageBB] = $this->createProgramWithStages($orgB->id);

        // Org A user tries to access Org B's program/stage
        [$userA, $orgA] = $this->bootUserWithOrg('Org A');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programB->id}/stages/{$stageBB->id}/dependencies", [
                'depends_on_program_stage_id' => $stageAB->id,
            ]);

        $response->assertStatus(404);
    }
}
