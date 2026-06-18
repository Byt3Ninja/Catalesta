<?php

declare(strict_types=1);

namespace Tests\Feature\Programs;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for program_policies + program_role_requirements sub-resources.
 *
 * Coverage:
 *   - Owner POST /api/v1/programs/{program}/policies → 201, persisted, scoped
 *   - Owner GET  /api/v1/programs/{program}/policies → 200, lists policies
 *   - Duplicate policy key for same program → 422 (DB unique enforced via FormRequest Rule::unique)
 *   - Owner POST /api/v1/programs/{program}/role-requirements → 201, persisted, scoped
 *   - Owner GET  /api/v1/programs/{program}/role-requirements → 200, lists role requirements
 *   - max_count < min_count → 422
 *   - Member WITHOUT programs.manage → 403
 *   - Cross-tenant: program of Org B with Org A header → 404
 */
final class ProgramConfigApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Boot a user+org, create a program via API, return [$user, $org, $programId].
     *
     * @return array{0: ExternalUser, 1: Organization, 2: string}
     */
    private function bootWithProgram(): array
    {
        [$user, $org] = $this->bootUserWithOrg();

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Config Program']);

        $programId = $response->json('data.id');

        return [$user, $org, $programId];
    }

    // -------------------------------------------------------------------------
    // program_policies — happy path
    // -------------------------------------------------------------------------

    public function test_owner_can_set_a_policy_on_their_program(): void
    {
        [$user, $org, $programId] = $this->bootWithProgram();

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/policies", [
                'key' => 'allow_late_applications',
                'value' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.key', 'allow_late_applications')
            ->assertJsonPath('data.value', true);

        $this->assertDatabaseHas('program_policies', [
            'program_id' => $programId,
            'organization_id' => $org->id,
            'key' => 'allow_late_applications',
        ]);
    }

    public function test_owner_can_list_policies_for_their_program(): void
    {
        [$user, $org, $programId] = $this->bootWithProgram();

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/policies", [
                'key' => 'max_cohort_size',
                'value' => 50,
            ])
            ->assertStatus(201);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/programs/{$programId}/policies");

        $response->assertStatus(200);
        $keys = collect($response->json('data'))->pluck('key')->toArray();
        $this->assertContains('max_cohort_size', $keys);
    }

    public function test_duplicate_policy_key_for_same_program_returns_422(): void
    {
        [$user, $org, $programId] = $this->bootWithProgram();

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/policies", [
                'key' => 'allow_late_applications',
                'value' => false,
            ])
            ->assertStatus(201);

        // Second attempt with same key on same program must fail with 422
        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/policies", [
                'key' => 'allow_late_applications',
                'value' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('key', $response->json('error.details'));
    }

    // -------------------------------------------------------------------------
    // program_role_requirements — happy path
    // -------------------------------------------------------------------------

    public function test_owner_can_set_a_role_requirement_on_their_program(): void
    {
        [$user, $org, $programId] = $this->bootWithProgram();

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/role-requirements", [
                'role_key' => 'mentor',
                'min_count' => 2,
                'max_count' => 5,
                'is_required' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.role_key', 'mentor')
            ->assertJsonPath('data.min_count', 2)
            ->assertJsonPath('data.max_count', 5)
            ->assertJsonPath('data.is_required', true);

        $this->assertDatabaseHas('program_role_requirements', [
            'program_id' => $programId,
            'organization_id' => $org->id,
            'role_key' => 'mentor',
            'min_count' => 2,
            'max_count' => 5,
        ]);
    }

    public function test_owner_can_list_role_requirements_for_their_program(): void
    {
        [$user, $org, $programId] = $this->bootWithProgram();

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/role-requirements", [
                'role_key' => 'advisor',
                'min_count' => 1,
            ])
            ->assertStatus(201);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/programs/{$programId}/role-requirements");

        $response->assertStatus(200);
        $keys = collect($response->json('data'))->pluck('role_key')->toArray();
        $this->assertContains('advisor', $keys);
    }

    // -------------------------------------------------------------------------
    // Validation — max_count < min_count → 422
    // -------------------------------------------------------------------------

    public function test_role_requirement_with_max_count_less_than_min_count_returns_422(): void
    {
        [$user, $org, $programId] = $this->bootWithProgram();

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/role-requirements", [
                'role_key' => 'investor',
                'min_count' => 5,
                'max_count' => 2,  // invalid: < min_count
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('max_count', $response->json('error.details'));
    }

    // -------------------------------------------------------------------------
    // Authorization: member WITHOUT programs.manage → 403
    // -------------------------------------------------------------------------

    public function test_member_without_manage_cannot_set_policy(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('Policy Perm Org');

        // Create a program directly, bypassing tenant scope
        $program = Program::withoutGlobalScope('tenant')->create([
            'name' => 'Restricted Program',
            'status' => ProgramStatus::Draft,
            'organization_id' => $org->id,
        ]);

        $member = $this->makeExternalUser();
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'external_user_id' => $member->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/policies", [
                'key' => 'some_policy',
                'value' => 'denied',
            ]);

        $response->assertStatus(403);
    }

    public function test_member_without_manage_cannot_set_role_requirement(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('Role Req Perm Org');

        $program = Program::withoutGlobalScope('tenant')->create([
            'name' => 'Restricted Program 2',
            'status' => ProgramStatus::Draft,
            'organization_id' => $org->id,
        ]);

        $member = $this->makeExternalUser();
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'external_user_id' => $member->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/role-requirements", [
                'role_key' => 'mentor',
                'min_count' => 1,
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: program of Org B, header Org A → 404
    // -------------------------------------------------------------------------

    public function test_cross_tenant_cannot_set_policy_on_other_orgs_program(): void
    {
        // Create Org B with its own program
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Config');

        $createResp = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Program Config'])
            ->assertStatus(201);

        $programBId = $createResp->json('data.id');

        // Create Org A user
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Config');

        // Org A user sends Org A header but targets Org B's program
        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programBId}/policies", [
                'key' => 'cross_tenant_key',
                'value' => 'injected',
            ]);

        // BelongsToTenant global scope makes Org B's program invisible → 404
        $this->assertContains($response->status(), [403, 404]);

        // Confirm nothing was written for Org B's program
        $this->assertDatabaseMissing('program_policies', [
            'program_id' => $programBId,
            'key' => 'cross_tenant_key',
        ]);
    }

    public function test_cross_tenant_cannot_set_role_requirement_on_other_orgs_program(): void
    {
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Role');

        $createResp = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Role Program'])
            ->assertStatus(201);

        $programBId = $createResp->json('data.id');

        [$userA, $orgA] = $this->bootUserWithOrg('Org A Role');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programBId}/role-requirements", [
                'role_key' => 'cross_role',
                'min_count' => 1,
            ]);

        $this->assertContains($response->status(), [403, 404]);

        $this->assertDatabaseMissing('program_role_requirements', [
            'program_id' => $programBId,
            'role_key' => 'cross_role',
        ]);
    }
}
