<?php

declare(strict_types=1);

namespace Tests\Feature\Programs;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Program CRUD + publish API.
 *
 * Coverage:
 *   - Owner POST /api/v1/programs → 201 + status=draft
 *   - Owner GET /api/v1/programs → lists program
 *   - Owner GET /api/v1/programs/{id} → 200
 *   - Owner PATCH /api/v1/programs/{id} → 200 (draft)
 *   - Owner PATCH /api/v1/programs/{id} → 200 (published — editable after publish)
 *   - Owner POST /api/v1/programs/{id}/publish → 200 + status=published
 *   - Member WITHOUT programs.manage POST → 403
 *   - Member WITHOUT programs.publish POST publish → 403
 *   - Cross-tenant: Org A user cannot GET/PATCH Org B program → 404 or 403
 */
final class ProgramApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy-path CRUD
    // -------------------------------------------------------------------------

    public function test_owner_can_create_program_with_draft_status(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Accelerator']);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Accelerator')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'status', 'description', 'settings', 'created_at', 'updated_at']]);

        $this->assertDatabaseHas('programs', [
            'name' => 'Accelerator',
            'status' => 'draft',
            'organization_id' => $org->id,
        ]);
    }

    public function test_owner_can_list_programs(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Listed Program'])
            ->assertStatus(201);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/programs');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Listed Program', $names);
    }

    public function test_owner_can_show_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $createResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Show Me']);

        $programId = $createResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/programs/{$programId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $programId)
            ->assertJsonPath('data.name', 'Show Me');
    }

    public function test_owner_can_update_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $createResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Old Name']);

        $programId = $createResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->patchJson("/api/v1/programs/{$programId}", [
                'name' => 'New Name',
                'description' => 'Updated desc',
                'settings' => ['cohort_cap' => 30],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.description', 'Updated desc');

        $this->assertDatabaseHas('programs', ['id' => $programId, 'name' => 'New Name']);
    }

    public function test_owner_can_publish_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $createResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Publish Me']);

        $programId = $createResponse->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'published']);
    }

    public function test_patch_works_on_published_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        // Create and publish
        $createResponse = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Published Editable']);

        $programId = $createResponse->json('data.id');

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/publish")
            ->assertStatus(200);

        // PATCH after publish must still return 200
        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->patchJson("/api/v1/programs/{$programId}", ['name' => 'Still Editable']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Still Editable');

        $this->assertDatabaseHas('programs', ['id' => $programId, 'name' => 'Still Editable', 'status' => 'published']);
    }

    // -------------------------------------------------------------------------
    // Authorization: member WITHOUT programs.manage → 403
    // -------------------------------------------------------------------------

    public function test_member_without_manage_cannot_create_program(): void
    {
        // Create an org with a separate owner (never activates a session for the owner)
        $org = $this->createBareOrg('Managed Org');

        // Add a bare member with no roles (no permissions)
        $member = $this->makeExternalUser();
        $memberMembership = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Unauthorized Program']);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Authorization: member WITHOUT programs.publish → 403 on publish
    // -------------------------------------------------------------------------

    public function test_member_without_publish_cannot_publish_program(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        // Create org and program directly (no API call, no session for any prior user)
        $org = $this->createBareOrg('Publish Perm Org');

        // Create a draft program directly in the DB under this org
        $program = new Program(['name' => 'To Be Published', 'status' => ProgramStatus::Draft]);
        $program->organization_id = $org->id;
        $program->save();

        $programId = $program->id;

        // Create a bare member with no roles (no programs.publish permission)
        $member = $this->makeExternalUser();
        $memberMembership = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        // Bare member attempts to publish — must 403
        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/publish");

        $response->assertStatus(403);

        // Status must still be draft
        $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'draft']);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: Org A user cannot GET/PATCH Org B program
    // -------------------------------------------------------------------------

    public function test_cross_tenant_get_program_is_blocked(): void
    {
        // Create org B program
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B');

        $createResponse = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Program'])
            ->assertStatus(201);

        $programBId = $createResponse->json('data.id');

        // Create org A user
        [$userA, $orgA] = $this->bootUserWithOrg('Org A');

        // User A sends OrgA header but tries to GET Org B's program by id
        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->getJson("/api/v1/programs/{$programBId}");

        // Must be 404 (BelongsToTenant scope filters it out) or 403 — NOT 200
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_cross_tenant_patch_program_is_blocked(): void
    {
        // Create org B program
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Patch');

        $createResponse = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Patch Program'])
            ->assertStatus(201);

        $programBId = $createResponse->json('data.id');
        $originalName = 'Org B Patch Program';

        // Create org A user
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Patch');

        // User A with OrgA header tries to PATCH Org B program
        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->patchJson("/api/v1/programs/{$programBId}", ['name' => 'Hijacked']);

        // Must be 404 or 403 — NOT 200
        $this->assertContains($response->status(), [403, 404]);

        // Confirm name was NOT changed
        $this->assertDatabaseHas('programs', ['id' => $programBId, 'name' => $originalName]);
    }
}
