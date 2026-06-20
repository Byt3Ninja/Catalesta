<?php

declare(strict_types=1);

namespace Tests\Feature\Programs;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramPolicyRecord;
use App\Modules\Programs\Domain\Models\ProgramRoleRequirement;
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
        $program = new Program(['name' => 'Restricted Program', 'status' => ProgramStatus::Draft]);
        $program->organization_id = $org->id;
        $program->save();

        $member = $this->makeExternalUser();
        $memberMembership = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

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

        $program = new Program(['name' => 'Restricted Program 2', 'status' => ProgramStatus::Draft]);
        $program->organization_id = $org->id;
        $program->save();

        $member = $this->makeExternalUser();
        $memberMembership2 = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $memberMembership2->organization_id = $org->id;
        $memberMembership2->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/role-requirements", [
                'role_key' => 'mentor',
                'min_count' => 1,
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Regression: unauthorized member with DUPLICATE key → 403 before unique query
    // -------------------------------------------------------------------------

    public function test_unauthorized_member_gets_403_not_422_on_duplicate_policy_key(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('Dup Key Org');

        // Create program directly (bypassing tenant scope)
        $program = new Program(['name' => 'Restricted Program', 'status' => ProgramStatus::Draft]);
        $program->organization_id = $org->id;
        $program->save();

        // Set up a policy directly in the database (so we can test the duplicate key scenario)
        $policy = new ProgramPolicyRecord(['program_id' => $program->id, 'key' => 'theme', 'value' => 'dark']);
        $policy->organization_id = $org->id;
        $policy->save();

        // Create a member of the org with NO permissions
        $member = $this->makeExternalUser();
        $memberMembership3 = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $memberMembership3->organization_id = $org->id;
        $memberMembership3->save();

        // This member attempts to POST the SAME key (duplicate)
        // CRITICAL: Without authorize() before the unique rule, this would hit
        // the unique-validation query and return 422. With the fix, it returns 403.
        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/policies", [
                'key' => 'theme',  // same key as above
                'value' => 'light',
            ]);

        // Prove authz fires BEFORE the unique-validation query
        $response->assertStatus(403);

        // Confirm nothing was written (authz rejected it entirely)
        $this->assertDatabaseCount('program_policies', 1); // only the initial policy
    }

    public function test_unauthorized_member_gets_403_not_422_on_duplicate_role_requirement_key(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('Dup Role Key Org');

        // Create program directly (bypassing tenant scope)
        $program = new Program(['name' => 'Restricted Program for Roles', 'status' => ProgramStatus::Draft]);
        $program->organization_id = $org->id;
        $program->save();

        // Set up a role requirement directly in the database (so we can test the duplicate key scenario)
        $roleReq = new ProgramRoleRequirement(['program_id' => $program->id, 'role_key' => 'mentor', 'min_count' => 1, 'max_count' => 5]);
        $roleReq->organization_id = $org->id;
        $roleReq->save();

        // Create a member of the org with NO permissions
        $member = $this->makeExternalUser();
        $memberMembership4 = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $memberMembership4->organization_id = $org->id;
        $memberMembership4->save();

        // This member attempts to POST the SAME role_key (duplicate)
        // CRITICAL: Without authorize() before the unique rule, this would hit
        // the unique-validation query and return 422. With the fix, it returns 403.
        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/role-requirements", [
                'role_key' => 'mentor',  // same key as above
                'min_count' => 2,
                'max_count' => 4,
            ]);

        // Prove authz fires BEFORE the unique-validation query
        $response->assertStatus(403);

        // Confirm nothing was written (authz rejected it entirely)
        $this->assertDatabaseCount('program_role_requirements', 1); // only the initial requirement
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: program of Org B, header Org A → 403 (tenant-scoped null→403)
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

        // Org A user sends Org A header but targets Org B's program.
        // Tenant-scoped authorize() finds null → returns false → 403 BEFORE any
        // unique-validation query runs. 404 is also acceptable (defense in depth).
        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programBId}/policies", [
                'key' => 'cross_tenant_key',
                'value' => 'injected',
            ]);

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

    // -------------------------------------------------------------------------
    // Cross-tenant key-existence leak: Org B has a key → Org A probes it → 403 not 422
    // -------------------------------------------------------------------------

    public function test_cross_tenant_duplicate_policy_key_returns_403_not_422(): void
    {
        // Org B creates a program and sets a policy key
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Leak Policy');

        $createResp = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Leak Program'])
            ->assertStatus(201);

        $programBId = $createResp->json('data.id');

        // Org B already has this key set
        $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programBId}/policies", [
                'key' => 'secret_key',
                'value' => 'secret_value',
            ])
            ->assertStatus(201);

        // Org A user with programs.manage in Org A tries the SAME key against Org B's program.
        // If withoutGlobalScope were used, authorize() would resolve the program and Gate would
        // succeed (Org A admin has programs.manage), then the unique-validation query would
        // expose whether 'secret_key' exists in Org B (422 vs pass) — information leak.
        // With tenant-scoped query: program → null → authorize returns false → 403, no query.
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Leak Policy');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programBId}/policies", [
                'key' => 'secret_key',  // same key that already exists in Org B
                'value' => 'probed',
            ]);

        // Neutral 404 (FR-004 / AR-6): cross-tenant access deterministically returns 404,
        // never 422, which would reveal the key exists in Org B. The security property
        // under test is "blocked before validation, no key-existence leak".
        $response->assertStatus(404);
        $this->assertNotEquals(422, $response->status(), 'Cross-tenant key-existence leak detected: got 422');

        // Nothing was written for Org B's program
        $this->assertDatabaseCount('program_policies', 1);
    }

    public function test_cross_tenant_duplicate_role_key_returns_403_not_422(): void
    {
        // Org B creates a program and sets a role requirement
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Leak Role');

        $createResp = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Leak Role Program'])
            ->assertStatus(201);

        $programBId = $createResp->json('data.id');

        $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programBId}/role-requirements", [
                'role_key' => 'secret_role',
                'min_count' => 1,
            ])
            ->assertStatus(201);

        // Org A user probes Org B's program with the same role_key.
        // tenant-scoped authorize() → program null → false → 403, no unique query.
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Leak Role');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programBId}/role-requirements", [
                'role_key' => 'secret_role',  // same role_key that already exists in Org B
                'min_count' => 2,
            ]);

        // Neutral 404 (FR-004 / AR-6): cross-tenant access deterministically returns 404,
        // never 422, which would reveal the role_key exists in Org B. The security property
        // under test is "blocked before validation, no role-key existence leak".
        $response->assertStatus(404);
        $this->assertNotEquals(422, $response->status(), 'Cross-tenant role-key existence leak detected: got 422');

        // Nothing was written for Org B's program
        $this->assertDatabaseCount('program_role_requirements', 1);
    }
}
