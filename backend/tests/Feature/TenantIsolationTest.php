<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Organizations\Application\CreateOrganization;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationRole;
use App\Shared\Tenancy\TenantContext;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mandatory tenant-isolation test suite (docs/12 / Task 7.1).
 *
 * Every assertion here proves a REAL security boundary:
 *   - HTTP middleware rejects cross-tenant access (403).
 *   - BelongsToTenant global scope filters rows by organization_id.
 *   - RBAC policy blocks operations when the required permission is absent.
 *   - The organization index only returns orgs the authenticated user belongs to.
 */
final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. Non-member with Org2 header → 403
    // -------------------------------------------------------------------------

    /**
     * A user who is a member of Org1 only, sending X-Organization-Id: <Org2 id>
     * to GET /api/v1/organizations/{org2} must receive 403.
     *
     * The tenant middleware verifies active membership for the header org.
     * Because the user has no membership in Org2 the middleware aborts with 403
     * before the controller is ever reached.
     */
    public function test_non_member_org_header_is_forbidden(): void
    {
        [$user] = $this->bootUserWithOrg('Org1');

        // Create a second org owned by a different user (test's primary user has no membership)
        $org2 = $this->createBareOrg('Org2');

        // Re-authenticate as the test user so actingAs state is clean for this assertion
        $this->actingAs($user, 'web');

        $this->withHeader('X-Organization-Id', $org2->id)
            ->getJson('/api/v1/organizations/'.$org2->id)
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 2. Reading a foreign org (no membership at all) → NOT 200
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/organizations/{org2} for an org the user doesn't belong to
     * must not return 200.  The tenant middleware must block the request.
     */
    public function test_reading_foreign_org_is_not_200(): void
    {
        [$user] = $this->bootUserWithOrg('My Org');

        $foreignOrg = $this->createBareOrg('Foreign Org');

        $this->actingAs($user, 'web');

        $response = $this->withHeader('X-Organization-Id', $foreignOrg->id)
            ->getJson('/api/v1/organizations/'.$foreignOrg->id);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 3. BelongsToTenant global scope: Org1 rows are invisible under Org2 context
    // -------------------------------------------------------------------------

    /**
     * A tenant-owned row (OrganizationRole) created under Org1 must NOT appear
     * when the TenantContext is set to Org2.
     *
     * This test exercises the BelongsToTenant scope directly — it bypasses HTTP
     * and sets the TenantContext in the container, then queries OrganizationRole
     * to verify scope exclusion.
     */
    public function test_belongs_to_tenant_scope_excludes_other_org_rows(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        // Build Org1 with its owner role
        $owner1 = $this->makeExternalUser();
        /** @var CreateOrganization $service */
        $service = $this->app->make(CreateOrganization::class);
        $org1 = $service->handle($owner1, 'Org1 Scope');

        // Build Org2 with its owner role
        $owner2 = $this->makeExternalUser();
        $org2 = $service->handle($owner2, 'Org2 Scope');

        // Confirm both orgs have an owner role (without any scope)
        $org1RoleCount = OrganizationRole::withoutGlobalScope('tenant')
            ->where('organization_id', $org1->id)
            ->count();
        $org2RoleCount = OrganizationRole::withoutGlobalScope('tenant')
            ->where('organization_id', $org2->id)
            ->count();

        $this->assertGreaterThan(0, $org1RoleCount, 'Org1 must have at least one role');
        $this->assertGreaterThan(0, $org2RoleCount, 'Org2 must have at least one role');

        // Set TenantContext to Org2: the BelongsToTenant scope should ONLY return Org2 rows
        $membership2 = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $org2->id)
            ->where('external_user_id', $owner2->id)
            ->firstOrFail();

        /** @var TenantContext $ctx */
        $ctx = $this->app->make(TenantContext::class);
        $ctx->setOrganization(
            $org2->id,
            $membership2,
            $membership2->effectivePermissionKeys(),
        );

        // Under Org2 context, OrganizationRole::all() must NOT include Org1 roles
        $visibleIds = OrganizationRole::all()->pluck('organization_id')->unique()->toArray();

        $this->assertNotContains(
            $org1->id,
            $visibleIds,
            'BelongsToTenant scope leaked Org1 roles into Org2 query — cross-tenant leak detected',
        );

        $this->assertContains(
            $org2->id,
            $visibleIds,
            'Org2 roles should be visible under Org2 TenantContext',
        );
    }

    // -------------------------------------------------------------------------
    // 4. Member WITHOUT organizations.manage cannot PATCH → 403
    // -------------------------------------------------------------------------

    /**
     * A user who is an active member of an org but has NO roles (and therefore
     * no permissions) must receive 403 when attempting PATCH /api/v1/organizations/{id}.
     *
     * State is created directly in the DB (not via API) to avoid session leakage
     * from a prior actingAs() call, following the same pattern as OrganizationApiTest.
     */
    public function test_member_without_manage_permission_cannot_patch_org(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        // Create the target org directly (no API, no session for any prior user)
        $org = Organization::create(['name' => 'Isolation Patch Org']);

        // Add the test member as active but with NO roles (empty permission set)
        $member = $this->makeExternalUser();
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'external_user_id' => $member->id,
            'status' => 'active',
        ]);

        // Tenant middleware will resolve the membership and set an empty permission list.
        // OrganizationPolicy::update() requires organizations.manage → must 403.
        $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->patchJson('/api/v1/organizations/'.$org->id, ['name' => 'Hijacked'])
            ->assertStatus(403);

        // Confirm the name was NOT changed
        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'name' => 'Isolation Patch Org']);
    }

    // -------------------------------------------------------------------------
    // 5. GET /api/v1/organizations returns ONLY the user's orgs
    // -------------------------------------------------------------------------

    /**
     * The index endpoint must return only the organizations the authenticated user
     * is a member of — not all organizations that exist in the database.
     */
    public function test_index_returns_only_own_orgs_not_others(): void
    {
        // User A: member of org1 only
        [$userA, $org1] = $this->bootUserWithOrg('User A Org');

        // A separate org owned by a different user — userA has no membership here
        $foreignOrg = $this->createBareOrg('Foreign Org');

        // Authenticate as userA and call the index
        $this->actingAs($userA, 'web');

        $response = $this->getJson('/api/v1/organizations');
        $response->assertStatus(200);

        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json('data') ?? [];
        $returnedIds = array_column($data, 'id');

        // userA's own org must appear
        $this->assertContains(
            $org1->id,
            $returnedIds,
            'Index must include orgs the user is a member of',
        );

        // The foreign org must NOT appear
        $this->assertNotContains(
            $foreignOrg->id,
            $returnedIds,
            'Index must NOT expose orgs the user has no membership in — cross-tenant leak detected',
        );
    }
}
