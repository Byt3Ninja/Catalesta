<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Enforce append-only on audit_logs at the DATABASE layer (NFR-012, Story 2.5
    // AC-4) — not just in app code. INSERT stays allowed; UPDATE and DELETE abort.
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
                BEGIN RAISE EXCEPTION 'audit_logs is append-only'; END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();
                CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only();
            SQL);

            return;
        }

        // SQLite (tests) and other RAISE-capable drivers.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
            BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END;

            CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
            BEGIN SELECT RAISE(ABORT, 'audit_logs is append-only'); END;
        SQL);
    }

    public function down(): void
    {
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        $on = $pgsql ? ' ON audit_logs' : '';

        DB::unprepared("DROP TRIGGER IF EXISTS audit_logs_no_update{$on};");
        DB::unprepared("DROP TRIGGER IF EXISTS audit_logs_no_delete{$on};");

        if ($pgsql) {
            DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_append_only();');
        }
    }
};
