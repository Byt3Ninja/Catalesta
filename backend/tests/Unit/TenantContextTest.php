<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Tenancy\TenantContext;
use Tests\TestCase;

final class TenantContextTest extends TestCase
{
    public function test_permission_checks_against_resolved_set(): void
    {
        $ctx = new TenantContext;
        $this->assertFalse($ctx->has());
        $this->assertFalse($ctx->can('members.manage'));
    }

    public function test_platform_admin_bypasses_permission_checks(): void
    {
        $ctx = new TenantContext;
        $ctx->actingAsPlatformAdmin(true);
        $this->assertTrue($ctx->can('anything'));
    }
}
