<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $isPlatformAdmin = false): ExternalUser
    {
        static $counter = 0;
        $counter++;

        return ExternalUser::create([
            'startup_gate_subject_id' => 'sub-test-'.$counter,
            'email' => "user{$counter}@example.com",
            'is_platform_admin' => $isPlatformAdmin,
        ]);
    }

    /**
     * Test 1: POST /api/v1/organizations → 201; org + owner role + membership created.
     */
    public function test_authenticated_user_can_create_organization(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $creator = $this->makeUser();

        $response = $this->actingAs($creator, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Acme']);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Acme')
            ->assertJsonPath('data.slug', 'acme')
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'branding', 'created_at', 'updated_at']]);

        $orgId = $response->json('data.id');

        // Owner role should exist
        $this->assertDatabaseHas('organization_roles', [
            'organization_id' => $orgId,
            'key' => 'owner',
            'is_system' => 1,
        ]);

        // Creator membership should be active
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $orgId,
            'external_user_id' => $creator->id,
            'status' => 'active',
        ]);

        // Creator should have organizations.manage in effective permissions
        $membership = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $orgId)
            ->where('external_user_id', $creator->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertContains('organizations.manage', $membership->effectivePermissionKeys());
    }

    /**
     * Test 2: PATCH /api/v1/organizations/{id} → 200 for the creator/owner.
     */
    public function test_owner_can_update_organization_name(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $creator = $this->makeUser();

        $createResponse = $this->actingAs($creator, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Old Name']);

        $orgId = $createResponse->json('data.id');

        $response = $this->actingAs($creator, 'web')
            ->withHeader('X-Organization-Id', $orgId)
            ->patchJson("/api/v1/organizations/{$orgId}", ['name' => 'New Name']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('organizations', ['id' => $orgId, 'name' => 'New Name']);
    }

    /**
     * Test 3: GET /api/v1/organizations → lists orgs the authenticated user belongs to.
     */
    public function test_index_returns_organizations_user_belongs_to(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $creator = $this->makeUser();

        $createResponse = $this->actingAs($creator, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'My Org']);

        $createResponse->assertStatus(201);
        $orgId = $createResponse->json('data.id');

        $response = $this->actingAs($creator, 'web')
            ->getJson('/api/v1/organizations');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($orgId, $ids);
    }

    /**
     * Test 4: Non-member accessing GET /api/v1/organizations/{id} with org header → 403.
     *
     * State is set up directly in the DB (not via API) to avoid Sanctum SPA session
     * leakage from a prior actingAs() call bleeding the creator's session into the
     * outsider's request.
     */
    public function test_non_member_cannot_view_organization(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $outsider = $this->makeUser();

        // Create org + creator membership directly (no API call, so no session is set)
        $org = Organization::create(['name' => 'Private Org']);
        $creator = $this->makeUser();
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'external_user_id' => $creator->id,
            'status' => 'active',
        ]);

        // outsider has no membership — tenant middleware must reject with 403
        $response = $this->actingAs($outsider, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/organizations/{$org->id}");

        $response->assertStatus(403);
    }

    /**
     * Test 5: Member WITHOUT organizations.manage cannot PATCH → 403.
     *
     * State is set up directly in the DB to avoid Sanctum SPA session leakage.
     */
    public function test_member_without_manage_permission_cannot_update_organization(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $member = $this->makeUser();

        // Set up org directly, no API call so no session is set for any prior user
        $org = Organization::create(['name' => 'Target Org']);

        // Add $member as an active member with NO roles (no permissions)
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'external_user_id' => $member->id,
            'status' => 'active',
        ]);

        // Tenant middleware will resolve $member's membership; TenantContext will have
        // empty permissions. OrganizationPolicy::update() checks organizations.manage → false → 403.
        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->patchJson("/api/v1/organizations/{$org->id}", ['name' => 'Hacked Name']);

        $response->assertStatus(403);
    }
}
