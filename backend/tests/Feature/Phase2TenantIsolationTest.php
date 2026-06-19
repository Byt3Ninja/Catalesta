<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Programs\Domain\Models\ProgramTemplate;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 consolidated tenant-isolation + authorization release-gate test suite.
 *
 * Three security matrices:
 *
 *   Matrix 1 — Cross-tenant 404/403 (Org B user + header → accessing Org A resource IDs).
 *              Any status other than 200/201 is a pass. A 200 with Org A data is a real leak.
 *
 *   Matrix 2 — List-endpoint global-scope invisibility (Org B owner lists, must not see Org A data).
 *
 *   Matrix 3 — Authorization 403 (Org B no-manage member acting on Org B own resources).
 *
 * Additive — does NOT modify TenantIsolationTest.php or any per-feature test.
 */
final class Phase2TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // MATRIX 1 — Cross-tenant resource access (Org B acts on Org A resource IDs)
    // =========================================================================

    // -------------------------------------------------------------------------
    // Programs (by ID)
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_program_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programA->id}");

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant GET program must not return 200 — data leak detected (got {$response->status()})",
        );
    }

    public function test_matrix1_cross_tenant_patch_program_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/programs/{$programA->id}", ['name' => 'Hijacked']);

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant PATCH program must not return 200 (got {$response->status()})",
        );

        $this->assertDatabaseHas('programs', ['id' => $programA->id, 'name' => 'Org A Program']);
    }

    public function test_matrix1_cross_tenant_publish_program_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/publish");

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant POST publish must not return 200 (got {$response->status()})",
        );
    }

    public function test_matrix1_cross_tenant_clone_program_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/clone", ['name' => 'Stolen Clone']);

        $this->assertNotContains(
            $response->status(),
            [200, 201],
            "Cross-tenant POST clone must not return 200/201 (got {$response->status()})",
        );
    }

    // -------------------------------------------------------------------------
    // Program sub-resources: policies
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_program_policies_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programA->id}/policies");

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant GET policies must not return 200 (got {$response->status()})",
        );
    }

    public function test_matrix1_cross_tenant_post_program_policy_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/policies", [
                'key' => 'injected_policy',
                'value' => true,
            ]);

        $this->assertNotContains(
            $response->status(),
            [200, 201],
            "Cross-tenant POST policy must not return 200/201 (got {$response->status()})",
        );
    }

    // -------------------------------------------------------------------------
    // Program sub-resources: role-requirements
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_program_role_requirements_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programA->id}/role-requirements");

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant GET role-requirements must not return 200 (got {$response->status()})",
        );
    }

    public function test_matrix1_cross_tenant_post_program_role_requirement_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/role-requirements", [
                'role_key' => 'hacker',
                'min_count' => 1,
                'max_count' => 10,
                'is_required' => true,
            ]);

        $this->assertNotContains(
            $response->status(),
            [200, 201],
            "Cross-tenant POST role-requirement must not return 200/201 (got {$response->status()})",
        );
    }

    // -------------------------------------------------------------------------
    // Program sub-resource: cohorts
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_post_program_cohort_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/cohorts", [
                'name' => 'Injected Cohort',
                'starts_at' => '2027-01-01',
                'ends_at' => '2027-06-30',
            ]);

        $this->assertNotContains(
            $response->status(),
            [200, 201],
            "Cross-tenant POST cohort must not return 200/201 (got {$response->status()})",
        );
    }

    // -------------------------------------------------------------------------
    // Cohorts (by ID)
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_cohort_returns_404_or_403(): void
    {
        [, , , $cohortA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/cohorts/{$cohortA->id}");

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant GET cohort must not return 200 (got {$response->status()})",
        );
    }

    public function test_matrix1_cross_tenant_patch_cohort_returns_404_or_403(): void
    {
        [, , , $cohortA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/cohorts/{$cohortA->id}", ['name' => 'Hijacked Cohort']);

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant PATCH cohort must not return 200 (got {$response->status()})",
        );

        $this->assertDatabaseHas('cohorts', ['id' => $cohortA->id, 'name' => 'Org A Cohort']);
    }

    // -------------------------------------------------------------------------
    // Stage sub-resources (nested under program)
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_program_stages_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programA->id}/stages");

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant GET stages must not return 200 (got {$response->status()})",
        );
    }

    public function test_matrix1_cross_tenant_post_program_stage_returns_404_or_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/stages", [
                'key' => 'injected',
                'name' => 'Injected Stage',
                'type' => 'application',
            ]);

        $this->assertNotContains(
            $response->status(),
            [200, 201],
            "Cross-tenant POST stage must not return 200/201 (got {$response->status()})",
        );
    }

    public function test_matrix1_cross_tenant_reorder_program_stages_returns_404_or_403(): void
    {
        [, , $programA, , $stageA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/stages/reorder", [
                'stage_ids' => [$stageA->id],
            ]);

        $this->assertNotContains(
            $response->status(),
            [200, 201],
            "Cross-tenant POST reorder must not return 200/201 (got {$response->status()})",
        );
    }

    // -------------------------------------------------------------------------
    // Stages (by ID)
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_patch_stage_returns_404_or_403(): void
    {
        [, , , , $stageA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/stages/{$stageA->id}", ['name' => 'Hijacked Stage']);

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant PATCH stage must not return 200 (got {$response->status()})",
        );
    }

    public function test_matrix1_cross_tenant_publish_stage_returns_404_or_403(): void
    {
        [, , , , $stageA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/stages/{$stageA->id}/publish");

        $this->assertNotEquals(
            200,
            $response->status(),
            "Cross-tenant POST stage publish must not return 200 (got {$response->status()})",
        );

        $this->assertDatabaseHas('program_stages', [
            'id' => $stageA->id,
            'current_published_version_id' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Program templates
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_instantiate_template_returns_404_or_403(): void
    {
        [, , , , , $templateA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/program-templates/{$templateA->id}/instantiate", [
                'name' => 'Stolen Program',
            ]);

        $this->assertNotContains(
            $response->status(),
            [200, 201],
            "Cross-tenant POST instantiate must not return 200/201 (got {$response->status()})",
        );
    }

    // =========================================================================
    // MATRIX 2 — List-endpoint global-scope invisibility
    // =========================================================================

    public function test_matrix2_programs_list_does_not_include_other_org_programs(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB List');

        // Create an Org B program so the list is non-empty
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Own Program'])
            ->assertStatus(201);

        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson('/api/v1/programs');

        $response->assertStatus(200);

        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json('data') ?? [];
        $returnedIds = array_column($data, 'id');

        $this->assertNotContains(
            $programA->id,
            $returnedIds,
            'GET /programs must NOT return Org A programs when acting as Org B — global-scope leak detected',
        );
    }

    public function test_matrix2_stages_list_does_not_include_other_org_stages(): void
    {
        [, , , , $stageA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB Stages List');

        // Org B needs its own program to list stages against (route is /programs/{program}/stages)
        $programB = new Program(['name' => 'Org B Program', 'status' => ProgramStatus::Draft]);
        $programB->organization_id = $orgB->id;
        $programB->save();

        // Create an Org B stage so the list is non-empty
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/stages", [
                'key' => 'orgb-stage',
                'name' => 'Org B Stage',
                'type' => 'application',
            ])
            ->assertStatus(201);

        // List stages for Org B's own program — must NOT include Org A's stage
        $response = $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programB->id}/stages");

        $response->assertStatus(200);

        /** @var array<int, array<string, mixed>> $stageData */
        $stageData = $response->json('data') ?? [];
        $returnedIds = array_column($stageData, 'id');

        $this->assertNotContains(
            $stageA->id,
            $returnedIds,
            'GET /programs/{program}/stages must NOT return Org A stages when acting as Org B',
        );
    }

    // =========================================================================
    // MATRIX 3 — Authorization 403 (Org B no-manage member, own Org B resources)
    // =========================================================================

    public function test_matrix3_member_without_manage_cannot_create_program(): void
    {
        [$member, $orgB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Unauthorized Program']);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_manage_cannot_patch_program(): void
    {
        [$member, $orgB, $programB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/programs/{$programB->id}", ['name' => 'Hijacked']);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_manage_cannot_publish_program(): void
    {
        [$member, $orgB, $programB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/publish");

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_manage_cannot_clone_program(): void
    {
        [$member, $orgB, $programB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/clone", ['name' => 'Stolen Clone']);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_manage_cannot_post_policy(): void
    {
        [$member, $orgB, $programB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/policies", [
                'key' => 'allow_late_applications',
                'value' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_manage_cannot_post_role_requirement(): void
    {
        [$member, $orgB, $programB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/role-requirements", [
                'role_key' => 'mentor',
                'min_count' => 1,
                'max_count' => 5,
                'is_required' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_cohorts_manage_cannot_create_cohort(): void
    {
        [$member, $orgB, $programB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/cohorts", [
                'name' => 'Unauthorized Cohort',
                'starts_at' => '2027-01-01',
                'ends_at' => '2027-06-30',
            ]);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_cohorts_manage_cannot_patch_cohort(): void
    {
        [$member, $orgB, , $cohortB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/cohorts/{$cohortB->id}", ['name' => 'Hijacked Cohort']);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_stages_manage_cannot_create_stage(): void
    {
        [$member, $orgB, $programB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/stages", [
                'key' => 'unauthorized',
                'name' => 'Unauthorized Stage',
                'type' => 'application',
            ]);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_stages_manage_cannot_reorder_stages(): void
    {
        [$member, $orgB, $programB, , $stageB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programB->id}/stages/reorder", [
                'stage_ids' => [$stageB->id],
            ]);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_stages_manage_cannot_patch_stage(): void
    {
        [$member, $orgB, , , $stageB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/stages/{$stageB->id}", ['name' => 'Hijacked Stage']);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_stages_manage_cannot_publish_stage(): void
    {
        [$member, $orgB, , , $stageB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/stages/{$stageB->id}/publish");

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_manage_cannot_create_template(): void
    {
        [$member, $orgB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/program-templates', [
                'name' => 'Unauthorized Template',
                'blueprint' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_matrix3_member_without_manage_cannot_instantiate_template(): void
    {
        [$member, $orgB, , , , $templateB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/program-templates/{$templateB->id}/instantiate", [
                'name' => 'Unauthorized Instance',
            ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // Setup helpers
    // =========================================================================

    /**
     * Build Org A with a full set of owned resources.
     *
     * Returns [$ownerA, $orgA, $programA, $cohortA, $stageA, $templateA].
     *
     * All resources are created directly in the DB (bypassing HTTP) to avoid
     * any session / auth-state bleed-over into the test assertions that follow.
     *
     * @return array{
     *   0: ExternalUser,
     *   1: Organization,
     *   2: Program,
     *   3: Cohort,
     *   4: ProgramStage,
     *   5: ProgramTemplate
     * }
     */
    private function setupOrgAData(): array
    {
        [$ownerA, $orgA] = $this->bootUserWithOrg('OrgA');

        // Program
        $programA = new Program(['name' => 'Org A Program', 'status' => ProgramStatus::Draft]);
        $programA->organization_id = $orgA->id;
        $programA->save();

        // Cohort
        $cohortA = new Cohort([
            'program_id' => $programA->id,
            'name' => 'Org A Cohort',
            'starts_at' => '2027-01-01',
            'ends_at' => '2027-06-30',
        ]);
        $cohortA->organization_id = $orgA->id;
        $cohortA->save();

        // Stage + draft version
        $stageA = new ProgramStage([
            'program_id' => $programA->id,
            'key' => 'orga-stage',
            'name' => 'Org A Stage',
            'type' => 'application',
            'order_index' => 0,
        ]);
        $stageA->organization_id = $orgA->id;
        $stageA->save();

        $versionA = new StageVersion([
            'program_stage_id' => $stageA->id,
            'status' => 'draft',
            'version_number' => 0,
        ]);
        $versionA->organization_id = $orgA->id;
        $versionA->save();

        // Program template
        $templateA = new ProgramTemplate([
            'name' => 'Org A Template',
            'slug' => 'org-a-template',
            'blueprint' => ['stages' => []],
        ]);
        $templateA->organization_id = $orgA->id;
        $templateA->save();

        return [$ownerA, $orgA, $programA, $cohortA, $stageA, $templateA];
    }

    /**
     * Build Org B with an owner, a bare member with NO permissions, and Org B owned resources.
     *
     * Returns [$member, $orgB, $programB, $cohortB, $stageB, $templateB].
     *
     * The $member has an active membership in $orgB but no roles — no programs.manage,
     * cohorts.manage, or stages.manage. All write operations against own Org B resources
     * must therefore return 403.
     *
     * @return array{
     *   0: ExternalUser,
     *   1: Organization,
     *   2: Program,
     *   3: Cohort,
     *   4: ProgramStage,
     *   5: ProgramTemplate
     * }
     */
    private function setupOrgBMemberData(): array
    {
        // Create Org B (its owner is irrelevant — bare member is the test actor)
        $orgB = $this->createBareOrg('OrgB Member');

        // Bare member: active membership, no roles → no permissions
        $member = $this->makeExternalUser();
        $membership = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $membership->organization_id = $orgB->id;
        $membership->save();

        // Org B program
        $programB = new Program(['name' => 'Org B Program', 'status' => ProgramStatus::Draft]);
        $programB->organization_id = $orgB->id;
        $programB->save();

        // Org B cohort
        $cohortB = new Cohort([
            'program_id' => $programB->id,
            'name' => 'Org B Cohort',
            'starts_at' => '2027-01-01',
            'ends_at' => '2027-06-30',
        ]);
        $cohortB->organization_id = $orgB->id;
        $cohortB->save();

        // Org B stage + draft version
        $stageB = new ProgramStage([
            'program_id' => $programB->id,
            'key' => 'orgb-stage',
            'name' => 'Org B Stage',
            'type' => 'application',
            'order_index' => 0,
        ]);
        $stageB->organization_id = $orgB->id;
        $stageB->save();

        $versionB = new StageVersion([
            'program_stage_id' => $stageB->id,
            'status' => 'draft',
            'version_number' => 0,
        ]);
        $versionB->organization_id = $orgB->id;
        $versionB->save();

        // Org B template
        $templateB = new ProgramTemplate([
            'name' => 'Org B Template',
            'slug' => 'org-b-template',
            'blueprint' => ['stages' => []],
        ]);
        $templateB->organization_id = $orgB->id;
        $templateB->save();

        return [$member, $orgB, $programB, $cohortB, $stageB, $templateB];
    }
}
