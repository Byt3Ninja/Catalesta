<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuditAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function seedLog(): AuditLog
    {
        return AuditLog::create([
            'actor_account_id' => '01HQEXTERNALUSER00000000AA',
            'organization_id' => '01HQORGANIZATION0000000000',
            'action' => AuditAction::ProgramPublished->value,
            'target_type' => 'program',
            'target_id' => '01HQPROGRAM000000000000000',
            'result' => 'success',
        ]);
    }

    public function test_an_enumerated_action_is_recorded_with_actor_org_and_timestamp(): void // AC-1
    {
        $log = $this->seedLog();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'program.published',
            'actor_account_id' => '01HQEXTERNALUSER00000000AA',
            'organization_id' => '01HQORGANIZATION0000000000',
        ]);
        $this->assertNotNull($log->fresh()->created_at, 'audit row carries a timestamp');
    }

    public function test_update_is_denied_at_the_db_layer(): void // ★ AC-4
    {
        $log = $this->seedLog();

        try {
            AuditLog::where('id', $log->id)->update(['action' => 'tampered']);
            $this->fail('expected the DB to reject an UPDATE on audit_logs');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame('program.published', $log->fresh()->action, 'row is unchanged');
    }

    public function test_delete_is_denied_at_the_db_layer(): void // ★ AC-4
    {
        $log = $this->seedLog();

        try {
            AuditLog::where('id', $log->id)->delete();
            $this->fail('expected the DB to reject a DELETE on audit_logs');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]); // still present
    }

    public function test_enforcement_is_at_the_db_not_the_model(): void // ★ AC-4 (raw bypass also blocked)
    {
        $log = $this->seedLog();

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update(['result' => 'tampered']);
    }
}
