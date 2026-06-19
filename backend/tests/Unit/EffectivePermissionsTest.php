<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationPermission;
use App\Modules\Organizations\Domain\Models\OrganizationRole;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EffectivePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * effectivePermissionKeys() returns the distinct permission keys
     * assigned to a member's roles — and nothing more.
     *
     * Global-scope note: BelongsToTenant adds a tenant scope that filters
     * by TenantContext::organizationId(). In this test we set the context
     * via app(TenantContext::class)->setOrganization() so the scope resolves
     * correctly when the method traverses roles → permissions. The membership
     * query itself uses withoutGlobalScope('tenant') internally in
     * effectivePermissionKeys() so it is self-contained and doesn't rely on
     * the context being set before calling the method.
     */
    public function test_effective_permission_keys_returns_assigned_permissions(): void
    {
        $org = Organization::create(['name' => 'Acme']);

        $user = ExternalUser::create([
            'startup_gate_subject_id' => 'sub-test-001',
            'email' => 'alice@example.com',
        ]);

        $permission = OrganizationPermission::create([
            'key' => 'members.manage',
            'description' => 'Manage members',
        ]);

        $role = new OrganizationRole(['key' => 'admin', 'name' => 'Admin']);
        $role->organization_id = $org->id;
        $role->save();

        // Attach the permission to the role via the pivot table
        $role->permissions()->attach($permission->id);

        $membership = new OrganizationMembership(['external_user_id' => $user->id, 'status' => 'active']);
        $membership->organization_id = $org->id;
        $membership->save();

        // Assign the role to the membership
        $membership->roles()->attach($role->id);

        $keys = $membership->effectivePermissionKeys();

        $this->assertContains('members.manage', $keys);
        $this->assertNotContains('roles.manage', $keys);
    }

    public function test_effective_permission_keys_returns_distinct_keys_across_multiple_roles(): void
    {
        $org = Organization::create(['name' => 'Beta Corp']);

        $user = ExternalUser::create([
            'startup_gate_subject_id' => 'sub-test-002',
            'email' => 'bob@example.com',
        ]);

        $perm1 = OrganizationPermission::create(['key' => 'members.invite']);
        $perm2 = OrganizationPermission::create(['key' => 'roles.manage']);

        $role1 = new OrganizationRole(['key' => 'inviter', 'name' => 'Inviter']);
        $role1->organization_id = $org->id;
        $role1->save();
        $role1->permissions()->attach($perm1->id);

        $role2 = new OrganizationRole(['key' => 'role-manager', 'name' => 'Role Manager']);
        $role2->organization_id = $org->id;
        $role2->save();
        $role2->permissions()->attach($perm2->id);
        // Also attach perm1 to role2 to test DISTINCT
        $role2->permissions()->attach($perm1->id);

        $membership = new OrganizationMembership(['external_user_id' => $user->id, 'status' => 'active']);
        $membership->organization_id = $org->id;
        $membership->save();

        $membership->roles()->attach([$role1->id, $role2->id]);

        $keys = $membership->effectivePermissionKeys();

        $this->assertContains('members.invite', $keys);
        $this->assertContains('roles.manage', $keys);
        // Distinct: members.invite should appear only once
        $this->assertCount(2, array_unique($keys));
        $this->assertSame(count($keys), count(array_unique($keys)), 'Keys must be distinct');
    }

    public function test_membership_implements_tenant_membership_contract(): void
    {
        $org = Organization::create(['name' => 'Gamma Inc']);

        $user = ExternalUser::create([
            'startup_gate_subject_id' => 'sub-test-003',
            'email' => 'carol@example.com',
        ]);

        $membership = new OrganizationMembership(['external_user_id' => $user->id, 'status' => 'active']);
        $membership->organization_id = $org->id;
        $membership->save();

        $this->assertSame($org->id, $membership->organizationId());
        $this->assertIsArray($membership->effectivePermissionKeys());
    }
}
