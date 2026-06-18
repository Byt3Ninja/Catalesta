<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Cohort CRUD API.
 *
 * Coverage:
 *   - Owner POST /api/v1/programs/{p}/cohorts {name, capacity:20} → 201 draft
 *   - Owner GET /api/v1/cohorts/{id} → 200
 *   - Owner PATCH /api/v1/cohorts/{id} status draft→open → 200
 *   - Window-ordering violation (enrollment_opens_at after enrollment_closes_at) → 422
 *   - Member WITHOUT cohorts.manage → 403
 *   - Cross-tenant: cohort/program of Org B, header Org A → 404/403
 */
final class CohortApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy-path: create
    // -------------------------------------------------------------------------

    public function test_owner_can_create_cohort_with_draft_status(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        // Create a program first
        $programResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Accelerator']);

        $programId = $programResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/cohorts", [
                'name' => 'Cohort Alpha',
                'capacity' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Cohort Alpha')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.capacity', 20)
            ->assertJsonStructure(['data' => [
                'id', 'name', 'slug', 'status', 'capacity',
                'enrollment_opens_at', 'enrollment_closes_at',
                'starts_at', 'ends_at', 'timeline',
                'program_id', 'organization_id',
                'created_at', 'updated_at',
            ]]);

        $this->assertDatabaseHas('cohorts', [
            'name' => 'Cohort Alpha',
            'status' => 'draft',
            'capacity' => 20,
            'organization_id' => $org->id,
            'program_id' => $programId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Happy-path: show
    // -------------------------------------------------------------------------

    public function test_owner_can_show_cohort(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $programResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Show Program']);

        $programId = $programResponse->json('data.id');

        $cohortResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/cohorts", ['name' => 'Show Cohort']);

        $cohortId = $cohortResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/cohorts/{$cohortId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $cohortId)
            ->assertJsonPath('data.name', 'Show Cohort');
    }

    // -------------------------------------------------------------------------
    // Happy-path: update status draft → open
    // -------------------------------------------------------------------------

    public function test_owner_can_update_cohort_status_to_open(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $programResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Update Program']);

        $programId = $programResponse->json('data.id');

        $cohortResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/cohorts", ['name' => 'Status Cohort']);

        $cohortId = $cohortResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->patchJson("/api/v1/cohorts/{$cohortId}", ['status' => 'open']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('cohorts', ['id' => $cohortId, 'status' => 'open']);
    }

    // -------------------------------------------------------------------------
    // Validation: enrollment window ordering violation → 422
    // -------------------------------------------------------------------------

    public function test_enrollment_opens_after_enrollment_closes_returns_422(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $programResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Window Program']);

        $programId = $programResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/cohorts", [
                'name' => 'Bad Window Cohort',
                'enrollment_opens_at' => '2026-09-01T00:00:00Z',
                'enrollment_closes_at' => '2026-08-01T00:00:00Z', // before opens — invalid
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Authorization: member without cohorts.manage → 403
    // -------------------------------------------------------------------------

    public function test_member_without_manage_cannot_create_cohort(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        // Create an org and program under the owner (not the member)
        $org = $this->createBareOrg('Managed Org');

        $owner = $this->makeExternalUser();
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'external_user_id' => $owner->id,
            'status' => 'active',
        ]);

        // Create program without API (bypass to avoid needing programs.manage for owner)
        $program = Program::withoutGlobalScope('tenant')->create([
            'name' => 'Auth Test Program',
            'status' => ProgramStatus::Draft,
            'organization_id' => $org->id,
        ]);

        // Add a bare member with no roles (no permissions)
        $member = $this->makeExternalUser();
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'external_user_id' => $member->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/cohorts", [
                'name' => 'Unauthorized Cohort',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: cohort of Org B, header Org A → 404
    // -------------------------------------------------------------------------

    public function test_cross_tenant_get_cohort_is_blocked(): void
    {
        // Create org B with a cohort
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Cohort');

        $programResponse = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Program']);

        $programId = $programResponse->json('data.id');

        $cohortResponse = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programId}/cohorts", ['name' => 'Org B Cohort']);

        $cohortBId = $cohortResponse->json('data.id');

        // Create org A user
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Cohort');

        // User A with OrgA header tries to GET Org B cohort by id — must 404 or 403
        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->getJson("/api/v1/cohorts/{$cohortBId}");

        $this->assertContains($response->status(), [403, 404]);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: creating cohort under a foreign-org program → 403/404
    // -------------------------------------------------------------------------

    public function test_cross_tenant_create_cohort_under_foreign_program_is_blocked(): void
    {
        // Org B creates a program
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Create');

        $programResponse = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Create Program'])
            ->assertStatus(201);

        $programBId = $programResponse->json('data.id');

        // Org A user tries to create cohort under Org B program
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Create');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programBId}/cohorts", [
                'name' => 'Cross-tenant Cohort',
            ]);

        // Must be 403 or 404 — never 201
        $this->assertContains($response->status(), [403, 404]);
    }
}
