<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Shared\Tenancy\TenantContext;
use Tests\TestCase;

final class TenantContextSystemTest extends TestCase
{
    public function test_is_system_defaults_false_and_run_as_system_toggles_then_restores(): void
    {
        $ctx = new TenantContext;
        $this->assertFalse($ctx->isSystem());
        $seen = $ctx->runAsSystem(function () use ($ctx) {
            return $ctx->isSystem();
        });
        $this->assertTrue($seen);
        $this->assertFalse($ctx->isSystem(), 'flag restored after the closure');
    }

    public function test_run_as_system_is_reentrant_and_restores_on_exception(): void
    {
        $ctx = new TenantContext;
        try {
            $ctx->runAsSystem(function () use ($ctx) {
                $ctx->runAsSystem(fn () => null);
                $this->assertTrue($ctx->isSystem(), 'still system inside outer after nested returns');
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }
        $this->assertFalse($ctx->isSystem(), 'flag restored even when the closure throws');
    }
}
