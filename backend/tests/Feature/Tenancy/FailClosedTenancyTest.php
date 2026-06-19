<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Tenancy\Exceptions\TenantContextMissingException;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FailClosedTenancyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reading a tenant-owned model with NO resolved tenant must return empty collection.
     * (fail-closed: no tenant => whereRaw('1 = 0'))
     */
    public function test_read_without_tenant_returns_nothing(): void
    {
        // Seed a program under a real tenant (system context bypasses the create-guard).
        $ctx = app(TenantContext::class);
        [, $org] = $this->bootUserWithOrg();
        $ctx->runAsSystem(function () use ($org): void {
            $p = new Program(['name' => 'A']);
            $p->organization_id = $org->id;
            $p->save();
        });

        // Drop the resolved scoped instance so the next resolution is a fresh context
        // with no tenant set and not in system mode.
        $this->app->forgetInstance(TenantContext::class);

        $this->assertCount(0, Program::all(), 'fail-closed: no tenant => no rows');
    }

    /**
     * Creating a tenant-owned model with no tenant context and no explicit
     * organization_id must throw TenantContextMissingException.
     */
    public function test_create_without_tenant_throws(): void
    {
        $this->app->forgetInstance(TenantContext::class);

        $this->expectException(TenantContextMissingException::class);
        Program::query()->create(['name' => 'Orphan']); // no ctx, no org, not system
    }

    /**
     * runAsSystem() must see rows from ALL tenants regardless of which tenant
     * (if any) is set in the ambient context.
     */
    public function test_run_as_system_sees_all_tenants(): void
    {
        $ctx = app(TenantContext::class);
        [, $a] = $this->bootUserWithOrg('Org A');
        $ctx->runAsSystem(function () use ($a): void {
            $p = new Program(['name' => 'PA']);
            $p->organization_id = $a->id;
            $p->save();
        });
        $b = $this->createBareOrg('Org B');
        $ctx->runAsSystem(function () use ($b): void {
            $p = new Program(['name' => 'PB']);
            $p->organization_id = $b->id;
            $p->save();
        });

        $count = $ctx->runAsSystem(fn () => Program::query()->count());
        $this->assertSame(2, $count);
    }
}
