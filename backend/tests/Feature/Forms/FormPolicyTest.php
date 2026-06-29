<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\Contracts\TenantMembership;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Unit-style policy tests for FormPolicy.
 *
 * Follows the OrganizationPolicyTest pattern: TenantContext is seeded directly
 * (no database, no bootUserWithOrg) because the policy reads only from
 * TenantContext::can(). Gate::forUser($user) resolves FormPolicy via the
 * registered Gate binding, which delegates to the context.
 */
final class FormPolicyTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    /** Resolve TenantContext and seed it with the given permission keys. */
    private function makeContext(string ...$permKeys): TenantContext
    {
        $membership = new class('org-001', $permKeys) implements TenantMembership
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

        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);
        $ctx->setOrganization('org-001', $membership, $permKeys);

        return $ctx;
    }

    /** Minimal Account stub — the policy receives it but does not inspect it. */
    private function makeUser(): Account
    {
        return new Account(['id' => 'user-001']);
    }

    // ──────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────

    public function test_member_with_forms_manage_may_create(): void
    {
        $this->makeContext('forms.manage');

        $this->assertTrue(Gate::forUser($this->makeUser())->allows('create', Form::class));
    }

    public function test_member_without_forms_manage_may_not_create(): void
    {
        $this->makeContext(); // no permissions

        $this->assertFalse(Gate::forUser($this->makeUser())->allows('create', Form::class));
    }

    public function test_any_member_may_view_any(): void
    {
        $this->makeContext(); // no special permission required for viewAny

        $this->assertTrue(Gate::forUser($this->makeUser())->allows('viewAny', Form::class));
    }

    public function test_member_without_forms_manage_may_not_update(): void
    {
        $this->makeContext(); // no permissions

        $form = new Form(['id' => 'form-001']);

        $this->assertFalse(Gate::forUser($this->makeUser())->allows('update', $form));
    }

    public function test_member_without_forms_manage_may_not_publish(): void
    {
        $this->makeContext(); // no permissions

        $form = new Form(['id' => 'form-001']);

        $this->assertFalse(Gate::forUser($this->makeUser())->allows('publish', $form));
    }
}
