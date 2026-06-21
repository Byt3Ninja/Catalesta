<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Organizations\Application\CreateOrganization;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationRole;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $isPlatformAdmin = false): Account
    {
        static $counter = 0;
        $counter++;

        return $this->makeAccount([
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
            'account_id' => $creator->id,
            'status' => 'active',
        ]);

        // Creator should have organizations.manage in effective permissions
        $membership = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $orgId)
            ->where('account_id', $creator->id)
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
     * Test 4: Non-member accessing GET /api/v1/organizations/{id} with org header → 404.
     *
     * Neutral 404 (FR-004 / AR-6): a non-member must not learn the org exists, so the
     * cross-tenant access path returns 404, not 403.
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
        $creatorMembership = new OrganizationMembership(['account_id' => $creator->id, 'status' => 'active']);
        $creatorMembership->organization_id = $org->id;
        $creatorMembership->save();

        // outsider has no membership in the header org — neutral 404 (no existence leak)
        $response = $this->actingAs($outsider, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/organizations/{$org->id}");

        $response->assertStatus(404);
    }

    /**
     * Test 4b (AR-6): a member of their OWN org cannot read a FOREIGN org by passing
     * their own valid header but a foreign org id in the URL → neutral 404, no data leak.
     */
    public function test_member_cannot_view_foreign_org_via_own_header(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        // The acting user owns their own org (valid X-Organization-Id header)
        [$user, $ownOrg] = $this->bootUserWithOrg('Home Org');

        // A foreign org the user has no membership in
        $foreignOrg = $this->createBareOrg('Foreign Org');

        $this->actingAs($user, 'web');

        $response = $this->withHeader('X-Organization-Id', $ownOrg->id)
            ->getJson("/api/v1/organizations/{$foreignOrg->id}");

        $response->assertStatus(404);
        // The foreign org's name must never appear in the response body
        $this->assertStringNotContainsString('Foreign Org', $response->getContent() ?: '');
    }

    /**
     * Test 4c (AC-4): a second org whose name derives to an existing slug is rejected
     * with a clean 422 validation error (not a 500 from the DB unique index).
     */
    public function test_duplicate_organization_name_is_rejected_with_422(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $first = $this->makeUser();
        $this->actingAs($first, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Acme Labs'])
            ->assertStatus(201);

        // A different user submits a name that derives to the same slug ("acme-labs")
        $second = $this->makeUser();
        $response = $this->actingAs($second, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Acme   Labs']);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('name', $response->json('error.details'));
    }

    /**
     * Test 4d (AC-4 race): a concurrent same-name create that slips past the FormRequest
     * uniqueness check and hits the DB slug unique index must surface a clean
     * ValidationException (→ 422), never a raw QueryException (→ 500).
     */
    public function test_slug_collision_race_in_service_throws_validation_exception(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        // An org with slug "acme" already exists (simulates the row the racing
        // request did not see during FormRequest validation).
        Organization::create(['name' => 'Acme']);

        $user = $this->makeUser();

        $this->expectException(ValidationException::class);

        $this->withoutTenantContext(fn () => app(CreateOrganization::class)->handle($user, 'Acme'));
    }

    /**
     * Test 6: Owner can list memberships → 200.
     */
    public function test_owner_can_list_memberships(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $owner = $this->makeUser();

        $createResponse = $this->actingAs($owner, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Membership Org']);

        $createResponse->assertStatus(201);
        $orgId = $createResponse->json('data.id');

        $response = $this->actingAs($owner, 'web')
            ->withHeader('X-Organization-Id', $orgId)
            ->getJson("/api/v1/organizations/{$orgId}/memberships");

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    /**
     * Test 7: Owner can add a member with a valid role_key → 201; membership + role persisted.
     */
    public function test_owner_can_create_membership_with_valid_role_key(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $owner = $this->makeUser();
        $newMember = $this->makeUser();

        $createResponse = $this->actingAs($owner, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Store Membership Org']);

        $createResponse->assertStatus(201);
        $orgId = $createResponse->json('data.id');

        // Retrieve the owner role key that was auto-created for this org
        $ownerRole = OrganizationRole::withoutGlobalScope('tenant')
            ->where('organization_id', $orgId)
            ->where('key', 'owner')
            ->first();

        $this->assertNotNull($ownerRole);

        $response = $this->actingAs($owner, 'web')
            ->withHeader('X-Organization-Id', $orgId)
            ->postJson("/api/v1/organizations/{$orgId}/memberships", [
                'account_id' => $newMember->id,
                'role_keys' => ['owner'],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $orgId,
            'account_id' => $newMember->id,
            'status' => 'active',
        ]);

        // Verify the role was attached
        $membership = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $orgId)
            ->where('account_id', $newMember->id)
            ->first();

        $this->assertNotNull($membership);
        $roleIds = $membership->roles()->pluck('organization_roles.id')->toArray();
        $this->assertContains($ownerRole->id, $roleIds);
    }

    /**
     * Test 8: Membership store with unknown role_key → 422.
     */
    public function test_membership_store_with_unknown_role_key_returns_422(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $owner = $this->makeUser();
        $newMember = $this->makeUser();

        $createResponse = $this->actingAs($owner, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Validation Org']);

        $createResponse->assertStatus(201);
        $orgId = $createResponse->json('data.id');

        $response = $this->actingAs($owner, 'web')
            ->withHeader('X-Organization-Id', $orgId)
            ->postJson("/api/v1/organizations/{$orgId}/memberships", [
                'account_id' => $newMember->id,
                'role_keys' => ['nonexistent-role-xyz'],
            ]);

        $response->assertStatus(422);
        // The app uses a custom exception renderer: validation errors land in error.details
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('role_keys', $response->json('error.details'));
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
        $memberMembership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        // Tenant middleware will resolve $member's membership; TenantContext will have
        // empty permissions. OrganizationPolicy::update() checks organizations.manage → false → 403.
        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->patchJson("/api/v1/organizations/{$org->id}", ['name' => 'Hacked Name']);

        $response->assertStatus(403);
    }
}
