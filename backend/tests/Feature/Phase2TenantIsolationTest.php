<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Identity\Domain\Models\Account;
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
 *   Matrix 1 — Cross-tenant isolation via a manage-capable Org B owner (actor has full
 *              permissions within Org B, so the ONLY thing that can block access is isolation).
 *              Two legitimate isolation mechanisms surface, depending on the endpoint:
 *
 *              404-by-scope (10 endpoints): the controller or route model binding resolves the
 *              resource via a GLOBAL-SCOPE findOrFail scoped to the current tenant (via
 *              X-Organization-Id middleware); the record is not found → 404.
 *
 *              403-by-authorize (7 endpoints): these endpoints perform a TENANT-SCOPED lookup
 *              inside their FormRequest authorize() method (e.g.
 *              `Cohort::query()->find($this->route('id'))` — no global scope), which returns
 *              null for a foreign-org ID → authorize() returns false → Laravel responds 403.
 *              This is the deliberate no-existence-leak pattern: 403 reveals nothing about
 *              whether the resource exists in another tenant.
 *
 *              Both outcomes prove a manage-capable foreign actor cannot reach Org A data.
 *              A 200 with Org A data is a real leak — stop and report.
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

    public function test_matrix1_cross_tenant_get_program_returns_404(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programA->id}")
            ->assertStatus(404);
    }

    public function test_matrix1_cross_tenant_patch_program_returns_404(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/programs/{$programA->id}", ['name' => 'Hijacked'])
            ->assertStatus(404);

        $this->assertDatabaseHas('programs', ['id' => $programA->id, 'name' => 'Org A Program']);
    }

    public function test_matrix1_cross_tenant_publish_program_returns_404(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/publish")
            ->assertStatus(404);

        $this->assertDatabaseHas('programs', ['id' => $programA->id, 'status' => ProgramStatus::Draft->value]);
    }

    public function test_matrix1_cross_tenant_clone_program_returns_404(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/clone", ['name' => 'Stolen Clone'])
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Program sub-resources: policies
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_program_policies_returns_404(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programA->id}/policies")
            ->assertStatus(404);
    }

    public function test_matrix1_cross_tenant_post_program_policy_returns_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        // 403-by-authorize: this endpoint's FormRequest authorize() performs a tenant-scoped
        // lookup (Program::query()->find($id) → null for a foreign-org ID → returns false → 403).
        // This is the deliberate no-existence-leak pattern; it still proves isolation.
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/policies", [
                'key' => 'injected_policy',
                'value' => true,
            ])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Program sub-resources: role-requirements
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_program_role_requirements_returns_404(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programA->id}/role-requirements")
            ->assertStatus(404);
    }

    public function test_matrix1_cross_tenant_post_program_role_requirement_returns_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        // 403-by-authorize: this endpoint's FormRequest authorize() performs a tenant-scoped
        // lookup (Program::query()->find($id) → null for a foreign-org ID → returns false → 403).
        // This is the deliberate no-existence-leak pattern; it still proves isolation.
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/role-requirements", [
                'role_key' => 'hacker',
                'min_count' => 1,
                'max_count' => 10,
                'is_required' => true,
            ])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Program sub-resource: cohorts
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_post_program_cohort_returns_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        // 403-by-authorize: this endpoint's FormRequest authorize() performs a tenant-scoped
        // lookup (Program::query()->find($id) → null for a foreign-org ID → returns false → 403).
        // This is the deliberate no-existence-leak pattern; it still proves isolation.
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/cohorts", [
                'name' => 'Injected Cohort',
                'starts_at' => '2027-01-01',
                'ends_at' => '2027-06-30',
            ])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Cohorts (by ID)
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_cohort_returns_404(): void
    {
        [, , , $cohortA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/cohorts/{$cohortA->id}")
            ->assertStatus(404);
    }

    public function test_matrix1_cross_tenant_patch_cohort_returns_403(): void
    {
        [, , , $cohortA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        // 403-by-authorize: this endpoint's FormRequest authorize() performs a tenant-scoped
        // lookup (Cohort::query()->find($id) → null for a foreign-org ID → returns false → 403).
        // This is the deliberate no-existence-leak pattern; it still proves isolation.
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/cohorts/{$cohortA->id}", ['name' => 'Hijacked Cohort'])
            ->assertStatus(403);

        $this->assertDatabaseHas('cohorts', ['id' => $cohortA->id, 'name' => 'Org A Cohort']);
    }

    // -------------------------------------------------------------------------
    // Stage sub-resources (nested under program)
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_get_program_stages_returns_404(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->getJson("/api/v1/programs/{$programA->id}/stages")
            ->assertStatus(404);
    }

    public function test_matrix1_cross_tenant_post_program_stage_returns_403(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        // 403-by-authorize: this endpoint's FormRequest authorize() performs a tenant-scoped
        // lookup (Program::query()->find($id) → null for a foreign-org ID → returns false → 403).
        // This is the deliberate no-existence-leak pattern; it still proves isolation.
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/stages", [
                'key' => 'injected',
                'name' => 'Injected Stage',
                'type' => 'application',
            ])
            ->assertStatus(403);
    }

    public function test_matrix1_cross_tenant_reorder_program_stages_returns_403(): void
    {
        [, , $programA, , $stageA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        // 403-by-authorize: this endpoint's FormRequest authorize() performs a tenant-scoped
        // lookup (Program::query()->find($id) → null for a foreign-org ID → returns false → 403).
        // This is the deliberate no-existence-leak pattern; it still proves isolation.
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programA->id}/stages/reorder", [
                'stage_ids' => [$stageA->id],
            ])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Stages (by ID)
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_patch_stage_returns_403(): void
    {
        [, , , , $stageA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        // 403-by-authorize: this endpoint's FormRequest authorize() performs a tenant-scoped
        // lookup (ProgramStage::query()->find($id) → null for a foreign-org ID → returns false → 403).
        // This is the deliberate no-existence-leak pattern; it still proves isolation.
        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->patchJson("/api/v1/stages/{$stageA->id}", ['name' => 'Hijacked Stage'])
            ->assertStatus(403);
    }

    public function test_matrix1_cross_tenant_publish_stage_returns_404(): void
    {
        [, , , , $stageA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/stages/{$stageA->id}/publish")
            ->assertStatus(404);

        $this->assertDatabaseHas('program_stages', [
            'id' => $stageA->id,
            'current_published_version_id' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Program templates
    // -------------------------------------------------------------------------

    public function test_matrix1_cross_tenant_instantiate_template_returns_404(): void
    {
        [, , , , , $templateA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB');

        $this->actingAs($userB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/program-templates/{$templateA->id}/instantiate", [
                'name' => 'Stolen Program',
            ])
            ->assertStatus(404);
    }

    // =========================================================================
    // MATRIX 2 — List-endpoint global-scope invisibility
    // =========================================================================

    public function test_matrix2_programs_list_does_not_include_other_org_programs(): void
    {
        [, , $programA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB List');

        // Create Org B program directly in DB — no HTTP session bleed-over risk
        $programB = new Program(['name' => 'Org B Own Program', 'status' => ProgramStatus::Draft]);
        $programB->organization_id = $orgB->id;
        $programB->save();

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

        $this->assertContains(
            $programB->id,
            $returnedIds,
            'GET /programs must return Org B own programs when acting as Org B',
        );
    }

    public function test_matrix2_stages_list_does_not_include_other_org_stages(): void
    {
        [, , , , $stageA] = $this->setupOrgAData();
        [$userB, $orgB] = $this->bootUserWithOrg('OrgB Stages List');

        // Org B program — direct DB creation, no HTTP session bleed-over risk
        $programB = new Program(['name' => 'Org B Program', 'status' => ProgramStatus::Draft]);
        $programB->organization_id = $orgB->id;
        $programB->save();

        // Org B stage — direct DB creation, no HTTP session bleed-over risk
        $stageB = new ProgramStage([
            'program_id' => $programB->id,
            'key' => 'orgb-stage',
            'name' => 'Org B Stage',
            'type' => 'application',
            'order_index' => 0,
        ]);
        $stageB->organization_id = $orgB->id;
        $stageB->save();

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

        $this->assertContains(
            $stageB->id,
            $returnedIds,
            'GET /programs/{program}/stages must return Org B own stages when acting as Org B',
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
        [$member, $orgB, $programB] = $this->setupOrgBMemberData();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/program-templates', [
                'name' => 'Unauthorized Template',
                'program_id' => $programB->id,
                'blueprint' => ['stages' => []],
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
     *   0: Account,
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
     *   0: Account,
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
        $member = $this->makeAccount();
        $membership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
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
