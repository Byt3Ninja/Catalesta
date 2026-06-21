<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Policies\MembershipPolicy;
use App\Modules\Organizations\Policies\OrganizationPolicy;
use App\Shared\Tenancy\Contracts\TenantMembership;
use App\Shared\Tenancy\TenantContext;
use Tests\TestCase;

/**
 * Unit tests for OrganizationPolicy and MembershipPolicy.
 *
 * These tests construct a TenantContext directly (no HTTP layer) to verify
 * that each policy method delegates to TenantContext::can() correctly.
 *
 * A minimal anonymous TenantMembership stub is used — it returns only the
 * permission keys we inject for each scenario. No database queries are made;
 * the policies read only from the TenantContext singleton.
 */
final class OrganizationPolicyTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /** Build a TenantMembership stub that returns the given permission keys. */
    private function makeMembership(string $orgId, string ...$keys): TenantMembership
    {
        return new class($orgId, $keys) implements TenantMembership
        {
            /** @param array<int,string> $keys */
            public function __construct(
                private readonly string $orgId,
                private readonly array $keys,
            ) {}

            public function organizationId(): string
            {
                return $this->orgId;
            }

            /** @return array<int,string> */
            public function effectivePermissionKeys(): array
            {
                return $this->keys;
            }
        };
    }

    /** Resolve TenantContext from the container, optionally seeding it. */
    private function makeContext(string ...$permKeys): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $ctx->setOrganization('org-001', $this->makeMembership('org-001', ...$permKeys), $permKeys);

        return $ctx;
    }

    private function makeAdminContext(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $ctx->actingAsPlatformAdmin(true);

        return $ctx;
    }

    /** Minimal Account stub — policy methods receive it but don't inspect it. */
    private function makeUser(): Account
    {
        return new Account(['id' => 'user-001']);
    }

    /** Minimal Organization stub. */
    private function makeOrg(): Organization
    {
        return new Organization(['id' => 'org-001', 'name' => 'Acme']);
    }

    // ──────────────────────────────────────────────────────────────────
    // OrganizationPolicy::view
    // ──────────────────────────────────────────────────────────────────

    public function test_org_view_returns_true_when_tenant_is_resolved(): void
    {
        $this->makeContext('organizations.manage');
        $policy = new OrganizationPolicy;

        $this->assertTrue($policy->view($this->makeUser(), $this->makeOrg()));
    }

    public function test_org_view_returns_true_even_without_manage_permission(): void
    {
        // view is open to any resolved member — no special permission required.
        $this->makeContext(); // no permissions
        $policy = new OrganizationPolicy;

        $this->assertTrue($policy->view($this->makeUser(), $this->makeOrg()));
    }

    // ──────────────────────────────────────────────────────────────────
    // OrganizationPolicy::update
    // ──────────────────────────────────────────────────────────────────

    public function test_org_update_returns_true_when_organizations_manage_granted(): void
    {
        $this->makeContext('organizations.manage');
        $policy = new OrganizationPolicy;

        $this->assertTrue($policy->update($this->makeUser(), $this->makeOrg()));
    }

    public function test_org_update_returns_false_when_organizations_manage_absent(): void
    {
        $this->makeContext('members.manage'); // different permission
        $policy = new OrganizationPolicy;

        $this->assertFalse($policy->update($this->makeUser(), $this->makeOrg()));
    }

    public function test_org_update_returns_false_when_no_permissions(): void
    {
        $this->makeContext();
        $policy = new OrganizationPolicy;

        $this->assertFalse($policy->update($this->makeUser(), $this->makeOrg()));
    }

    public function test_org_update_returns_true_for_platform_admin(): void
    {
        $this->makeAdminContext();
        $policy = new OrganizationPolicy;

        $this->assertTrue($policy->update($this->makeUser(), $this->makeOrg()));
    }

    // ──────────────────────────────────────────────────────────────────
    // MembershipPolicy::viewAny
    // ──────────────────────────────────────────────────────────────────

    public function test_membership_view_any_returns_true_when_members_manage_granted(): void
    {
        $this->makeContext('members.manage');
        $policy = new MembershipPolicy;

        $this->assertTrue($policy->viewAny($this->makeUser()));
    }

    public function test_membership_view_any_returns_false_when_members_manage_absent(): void
    {
        $this->makeContext('members.invite'); // invite only, no manage
        $policy = new MembershipPolicy;

        $this->assertFalse($policy->viewAny($this->makeUser()));
    }

    public function test_membership_view_any_returns_true_for_platform_admin(): void
    {
        $this->makeAdminContext();
        $policy = new MembershipPolicy;

        $this->assertTrue($policy->viewAny($this->makeUser()));
    }

    // ──────────────────────────────────────────────────────────────────
    // MembershipPolicy::create
    // ──────────────────────────────────────────────────────────────────

    public function test_membership_create_returns_true_when_members_invite_granted(): void
    {
        $this->makeContext('members.invite');
        $policy = new MembershipPolicy;

        $this->assertTrue($policy->create($this->makeUser()));
    }

    public function test_membership_create_returns_true_when_members_manage_granted(): void
    {
        $this->makeContext('members.manage');
        $policy = new MembershipPolicy;

        $this->assertTrue($policy->create($this->makeUser()));
    }

    public function test_membership_create_returns_true_when_both_invite_and_manage_granted(): void
    {
        $this->makeContext('members.invite', 'members.manage');
        $policy = new MembershipPolicy;

        $this->assertTrue($policy->create($this->makeUser()));
    }

    public function test_membership_create_returns_false_when_neither_invite_nor_manage_granted(): void
    {
        $this->makeContext('organizations.manage'); // irrelevant permission
        $policy = new MembershipPolicy;

        $this->assertFalse($policy->create($this->makeUser()));
    }

    public function test_membership_create_returns_false_when_no_permissions(): void
    {
        $this->makeContext();
        $policy = new MembershipPolicy;

        $this->assertFalse($policy->create($this->makeUser()));
    }

    public function test_membership_create_returns_true_for_platform_admin(): void
    {
        $this->makeAdminContext();
        $policy = new MembershipPolicy;

        $this->assertTrue($policy->create($this->makeUser()));
    }
}
