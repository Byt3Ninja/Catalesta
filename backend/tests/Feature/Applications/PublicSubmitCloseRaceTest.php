<?php

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Story 2.7 ★ FR-033 close-race guarantee, proven on Postgres.
 *
 * The submit re-checks the cohort open-state via `Cohort::lockForUpdate()` inside
 * its transaction so a concurrent `CloseCohort` cannot interleave. That row lock
 * is a no-op on SQLite, so the default in-memory suite can NOT exercise it — this
 * test is pgsql-gated and verifies the real serialization with two connections.
 *
 * Uses DatabaseMigrations (not RefreshDatabase) so the cohort is COMMITTED and
 * therefore visible to the second connection — a per-test transaction would hide
 * it. Run against a throwaway pgsql database, e.g.:
 *   DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=catalesta_test \
 *   DB_USERNAME=postgres DB_PASSWORD=password ./vendor/bin/phpunit \
 *   --filter PublicSubmitCloseRaceTest
 */
final class PublicSubmitCloseRaceTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        // Skip BEFORE parent::setUp() so DatabaseMigrations never runs on SQLite
        // (the in-memory driver also can't replay the outbox down() migrations).
        $driver = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('FR-033 row-lock serialization is a Postgres guarantee; lockForUpdate is a no-op on SQLite.');
        }

        parent::setUp();
    }

    public function test_close_cohort_blocks_while_a_submit_holds_the_cohort_row_lock(): void
    {
        $org = $this->createBareOrg();
        $cohort = $this->withoutTenantContext(function () use ($org): Cohort {
            $c = new Cohort([
                'program_id' => (string) Str::ulid(),
                'form_version_id' => 'form-v1',
                'name' => 'Race Cohort',
                'status' => CohortStatus::Open,
            ]);
            $c->setAttribute('organization_id', $org->id);
            $c->save(); // committed (DatabaseMigrations has no wrapping txn)

            return $c;
        });

        // A second, independent connection to the same database.
        config(['database.connections.pg_b' => config('database.connections.'.config('database.default'))]);
        DB::purge('pg_b');

        // Connection A: simulate an in-flight submit holding the cohort row lock
        // exactly as SubmitApplication::write() does — under runAsSystem, so the
        // tenant global scope doesn't filter the row out (and lock nothing). The
        // lock is transaction-scoped, so it persists after the closure returns.
        DB::beginTransaction();
        app(TenantContext::class)->runAsSystem(fn () => Cohort::lockForUpdate()->find($cohort->id));

        // Connection B: a CloseCohort-style UPDATE must NOT acquire the locked row.
        DB::connection('pg_b')->statement("SET lock_timeout = '750ms'");
        $blocked = false;
        try {
            DB::connection('pg_b')->update('update cohorts set status = ? where id = ?', ['closed', $cohort->id]);
        } catch (QueryException $e) {
            $msg = strtolower($e->getMessage());
            $blocked = str_contains($msg, 'lock timeout') || str_contains($msg, 'canceling statement');
        } finally {
            DB::rollBack(); // release A's lock
        }

        $this->assertTrue(
            $blocked,
            'a CloseCohort UPDATE cannot proceed while a submit holds Cohort::lockForUpdate() (FR-033 serialization)',
        );

        // Sanity: once the lock is released, the same UPDATE succeeds — proving the
        // block above was the lock, not a broken query.
        $affected = DB::connection('pg_b')->update('update cohorts set status = ? where id = ?', ['closed', $cohort->id]);
        $this->assertSame(1, $affected);
    }
}
