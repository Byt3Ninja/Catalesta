<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Audit\AuditLog;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_an_audit_entry(): void
    {
        app(AuditLogger::class)->record('organization.created', 'organization', '01ABC', [], ['name' => 'Acme']);
        $this->assertDatabaseCount('audit_logs', 1);
        $log = AuditLog::first();
        $this->assertSame('organization.created', $log->action);
        $this->assertSame(['name' => 'Acme'], $log->after);
    }
}
