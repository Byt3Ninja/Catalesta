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
 * Feature tests for Track CRUD API.
 *
 * Coverage:
 *   - Owner POST /api/v1/programs/{program}/tracks → 201
 *   - Owner GET /api/v1/programs/{program}/tracks → 200 (lists)
 *   - Owner PATCH /api/v1/tracks/{id} → 200
 *   - Owner DELETE /api/v1/tracks/{id} → 204
 *   - Duplicate key in same program → 422
 *   - Member WITHOUT programs.manage → 403
 *   - Cross-tenant {program} on store/list → 404
 *   - Cross-tenant {id} on patch/delete → 404
 */
final class TrackApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy-path CRUD
    // -------------------------------------------------------------------------

    public function test_owner_can_create_track_returns_201(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $programId = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Accel'])
            ->assertStatus(201)
            ->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/tracks", [
                'key' => 'tech',
                'name' => 'Technology',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.key', 'tech')
            ->assertJsonPath('data.name', 'Technology')
            ->assertJsonStructure(['data' => ['id', 'program_id', 'key', 'name', 'description', 'order_index', 'created_at', 'updated_at']]);

        $this->assertDatabaseHas('tracks', [
            'key' => 'tech',
            'name' => 'Technology',
            'program_id' => $programId,
            'organization_id' => $org->id,
        ]);
    }

    public function test_owner_can_list_tracks_for_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $programId = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Accel'])
            ->assertStatus(201)
            ->json('data.id');

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/tracks", [
                'key' => 'health',
                'name' => 'Health',
            ])
            ->assertStatus(201);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/programs/{$programId}/tracks");

        $response->assertStatus(200);
        $keys = collect($response->json('data'))->pluck('key')->toArray();
        $this->assertContains('health', $keys);
    }

    public function test_owner_can_patch_track(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $programId = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Accel'])
            ->assertStatus(201)
            ->json('data.id');

        $trackId = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/tracks", [
                'key' => 'fin',
                'name' => 'Finance',
            ])
            ->assertStatus(201)
            ->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->patchJson("/api/v1/tracks/{$trackId}", [
                'name' => 'Fintech',
                'description' => 'Financial technology track',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Fintech')
            ->assertJsonPath('data.description', 'Financial technology track');

        $this->assertDatabaseHas('tracks', ['id' => $trackId, 'name' => 'Fintech']);
    }

    public function test_owner_can_delete_track(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $programId = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Accel'])
            ->assertStatus(201)
            ->json('data.id');

        $trackId = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/tracks", [
                'key' => 'agri',
                'name' => 'Agriculture',
            ])
            ->assertStatus(201)
            ->json('data.id');

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->deleteJson("/api/v1/tracks/{$trackId}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tracks', ['id' => $trackId]);
    }

    // -------------------------------------------------------------------------
    // Duplicate key in same program → 422
    // -------------------------------------------------------------------------

    public function test_duplicate_key_in_same_program_returns_422(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $programId = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/programs', ['name' => 'Accel'])
            ->assertStatus(201)
            ->json('data.id');

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/tracks", [
                'key' => 'dup',
                'name' => 'First',
            ])
            ->assertStatus(201);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$programId}/tracks", [
                'key' => 'dup',
                'name' => 'Second',
            ]);

        // The app's error envelope puts validation errors under error.details (not errors.*)
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['key']]]);
    }

    // -------------------------------------------------------------------------
    // Authorization: member WITHOUT programs.manage → 403
    // -------------------------------------------------------------------------

    public function test_member_without_manage_cannot_create_track(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('NoManage Org');

        // Create a program directly (bypass HTTP)
        $program = new Program(['name' => 'No-Manage Program', 'status' => ProgramStatus::Draft]);
        $program->organization_id = $org->id;
        $program->save();

        // Bare member with no roles
        $member = $this->makeAccount();
        $membership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $membership->organization_id = $org->id;
        $membership->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$program->id}/tracks", [
                'key' => 'denied',
                'name' => 'Denied',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: {program} on store/list → 404
    // -------------------------------------------------------------------------

    public function test_cross_tenant_store_track_is_blocked(): void
    {
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Tracks');

        $programBId = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Program'])
            ->assertStatus(201)
            ->json('data.id');

        [$userA, $orgA] = $this->bootUserWithOrg('Org A Tracks');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programBId}/tracks", [
                'key' => 'xtrack',
                'name' => 'Cross Track',
            ]);

        // BelongsToTenant scope or policy guard blocks cross-tenant — 404 or 403, never 200
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_cross_tenant_list_tracks_is_blocked(): void
    {
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B List');

        $programBId = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B List Program'])
            ->assertStatus(201)
            ->json('data.id');

        [$userA, $orgA] = $this->bootUserWithOrg('Org A List');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->getJson("/api/v1/programs/{$programBId}/tracks");

        // BelongsToTenant scope or policy guard blocks cross-tenant — 404 or 403, never 200
        $this->assertContains($response->status(), [403, 404]);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: {id} on patch/delete → 404
    // -------------------------------------------------------------------------

    public function test_cross_tenant_patch_track_is_blocked(): void
    {
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Patch Track');

        $programBId = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Patch'])
            ->assertStatus(201)
            ->json('data.id');

        $trackBId = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programBId}/tracks", [
                'key' => 'b-track',
                'name' => 'B Track',
            ])
            ->assertStatus(201)
            ->json('data.id');

        [$userA, $orgA] = $this->bootUserWithOrg('Org A Patch Track');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->patchJson("/api/v1/tracks/{$trackBId}", ['name' => 'Hijacked']);

        // BelongsToTenant scope or policy guard blocks cross-tenant — 404 or 403, never 200
        $this->assertContains($response->status(), [403, 404]);

        $this->assertDatabaseHas('tracks', ['id' => $trackBId, 'name' => 'B Track']);
    }

    public function test_cross_tenant_delete_track_is_blocked(): void
    {
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Del Track');

        $programBId = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Del'])
            ->assertStatus(201)
            ->json('data.id');

        $trackBId = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson("/api/v1/programs/{$programBId}/tracks", [
                'key' => 'del-track',
                'name' => 'Del Track',
            ])
            ->assertStatus(201)
            ->json('data.id');

        [$userA, $orgA] = $this->bootUserWithOrg('Org A Del Track');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->deleteJson("/api/v1/tracks/{$trackBId}");

        // BelongsToTenant scope or policy guard blocks cross-tenant — 404 or 403, never 200
        $this->assertContains($response->status(), [403, 404]);

        $this->assertDatabaseHas('tracks', ['id' => $trackBId]);
    }
}
