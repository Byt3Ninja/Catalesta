<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Enforce append-only on learning_events at the DATABASE layer (mirrors
    // audit_logs, Story 2.5). INSERT stays allowed; UPDATE and DELETE abort.
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION learning_events_append_only() RETURNS trigger AS $$
                BEGIN RAISE EXCEPTION 'learning_events is append-only'; END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER learning_events_no_update BEFORE UPDATE ON learning_events
                    FOR EACH ROW EXECUTE FUNCTION learning_events_append_only();
                CREATE TRIGGER learning_events_no_delete BEFORE DELETE ON learning_events
                    FOR EACH ROW EXECUTE FUNCTION learning_events_append_only();
            SQL);

            return;
        }

        // SQLite (tests) and other RAISE-capable drivers.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER learning_events_no_update BEFORE UPDATE ON learning_events
            BEGIN SELECT RAISE(ABORT, 'learning_events is append-only'); END;

            CREATE TRIGGER learning_events_no_delete BEFORE DELETE ON learning_events
            BEGIN SELECT RAISE(ABORT, 'learning_events is append-only'); END;
        SQL);
    }

    public function down(): void
    {
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        $on = $pgsql ? ' ON learning_events' : '';

        DB::unprepared("DROP TRIGGER IF EXISTS learning_events_no_update{$on};");
        DB::unprepared("DROP TRIGGER IF EXISTS learning_events_no_delete{$on};");

        if ($pgsql) {
            DB::unprepared('DROP FUNCTION IF EXISTS learning_events_append_only();');
        }
    }
};
