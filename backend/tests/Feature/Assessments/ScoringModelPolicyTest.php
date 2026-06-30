<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\Contracts\TenantMembership;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class ScoringModelPolicyTest extends TestCase
{
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

    private function makeUser(): Account
    {
        return new Account(['id' => 'user-001']);
    }

    public function test_member_with_assessments_manage_may_create(): void
    {
        $this->makeContext('assessments.manage');
        $this->assertTrue(Gate::forUser($this->makeUser())->allows('create', ScoringModel::class));
    }

    public function test_member_without_assessments_manage_may_not_create(): void
    {
        $this->makeContext();
        $this->assertFalse(Gate::forUser($this->makeUser())->allows('create', ScoringModel::class));
    }

    public function test_any_member_may_view_any(): void
    {
        $this->makeContext();
        $this->assertTrue(Gate::forUser($this->makeUser())->allows('viewAny', ScoringModel::class));
    }

    public function test_member_without_assessments_manage_may_not_update(): void
    {
        $this->makeContext();
        $model = new ScoringModel(['id' => 'sm-001']);
        $this->assertFalse(Gate::forUser($this->makeUser())->allows('update', $model));
    }

    public function test_member_without_assessments_manage_may_not_publish(): void
    {
        $this->makeContext();
        $model = new ScoringModel(['id' => 'sm-001']);
        $this->assertFalse(Gate::forUser($this->makeUser())->allows('publish', $model));
    }
}
