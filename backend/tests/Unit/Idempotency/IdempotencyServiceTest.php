<?php

declare(strict_types=1);

namespace Tests\Unit\Idempotency;

use App\Shared\Idempotency\Exceptions\IdempotencyConflictException;
use App\Shared\Idempotency\Exceptions\IdempotencyInFlightException;
use App\Shared\Idempotency\Exceptions\ResponseTooLargeException;
use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Idempotency\IdempotencyService;
use App\Shared\Idempotency\IdempotencyStatus;
use App\Shared\Idempotency\RequestFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class IdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'idempotency.ttl_seconds' => 86400,
            'idempotency.lock_timeout_seconds' => 60,
            'idempotency.max_response_bytes' => 1024,
        ]);
        $this->service = app(IdempotencyService::class);
    }

    /** A counter-backed closure so we can prove "ran exactly once". */
    private function counter(mixed $return = 'ok'): array
    {
        $calls = 0;
        $fn = function () use (&$calls, $return) {
            $calls++;

            return $return;
        };

        return [$fn, function () use (&$calls) {
            return $calls;
        }];
    }

    public function test_same_key_and_fingerprint_runs_once_and_replays(): void // AC-2
    {
        [$fn, $calls] = $this->counter('result-A');

        $first = $this->service->remember('scope', 'k1', 'fp', $fn);
        $second = $this->service->remember('scope', 'k1', 'fp', $fn);

        $this->assertSame('result-A', $first);
        $this->assertSame('result-A', $second);
        $this->assertSame(1, $calls(), 'fn must run exactly once');
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_same_key_different_fingerprint_throws_conflict_and_does_not_rerun(): void // AC-3
    {
        [$fn, $calls] = $this->counter();
        $this->service->remember('scope', 'k1', 'fp-A', $fn);

        try {
            $this->service->remember('scope', 'k1', 'fp-B', $fn);
            $this->fail('expected IdempotencyConflictException');
        } catch (IdempotencyConflictException) {
            $this->assertSame(1, $calls(), 'mismatch must not re-run fn');
        }
    }

    public function test_scope_isolates_identical_keys(): void // AC-6
    {
        [$fn, $calls] = $this->counter();

        $this->service->remember('scope-A', 'shared-key', 'fp', $fn);
        $this->service->remember('scope-B', 'shared-key', 'fp', $fn);

        $this->assertSame(2, $calls(), 'same key under different scopes = two effects');
        $this->assertDatabaseCount('idempotency_keys', 2);
    }

    public function test_different_actor_cannot_replay_another_actors_response(): void // AC-7
    {
        [$fn, $calls] = $this->counter('actor-A-secret');
        $fpA = RequestFingerprint::for('actor-A', ['amount' => 100]);
        $fpB = RequestFingerprint::for('actor-B', ['amount' => 100]);

        $this->service->remember('payments', 'key-1', $fpA, $fn);

        $this->expectException(IdempotencyConflictException::class);
        try {
            $this->service->remember('payments', 'key-1', $fpB, $fn);
        } finally {
            $this->assertSame(1, $calls(), 'actor B must never replay actor A');
        }
    }

    public function test_in_flight_duplicate_returns_409(): void // AC-8
    {
        // A fresh, non-stale claim is already present.
        IdempotencyKey::create([
            'scope' => 'scope', 'key' => 'k1', 'request_fingerprint' => 'fp',
            'status' => IdempotencyStatus::Claimed->value,
            'locked_at' => now(), 'expires_at' => now()->addDay(),
        ]);

        [$fn, $calls] = $this->counter();
        $this->expectException(IdempotencyInFlightException::class);
        try {
            $this->service->remember('scope', 'k1', 'fp', $fn);
        } finally {
            $this->assertSame(0, $calls());
        }
    }

    public function test_fn_failure_releases_the_key_so_a_retry_runs_again(): void // AC-9
    {
        $calls = 0;
        $throwing = function () use (&$calls) {
            $calls++;
            throw new RuntimeException('boom');
        };

        try {
            $this->service->remember('scope', 'k1', 'fp', $throwing);
        } catch (RuntimeException) {
            // expected
        }
        $this->assertDatabaseCount('idempotency_keys', 0); // failed claim must be released

        // A genuine retry runs again (no cached-failure replay).
        try {
            $this->service->remember('scope', 'k1', 'fp', $throwing);
        } catch (RuntimeException) {
        }
        $this->assertSame(2, $calls);
    }

    public function test_oversize_response_fails_closed_without_completing(): void // AC-10
    {
        $big = str_repeat('x', 2048); // > max_response_bytes (1024)
        $fn = fn () => $big;

        $this->expectException(ResponseTooLargeException::class);
        try {
            $this->service->remember('scope', 'k1', 'fp', $fn);
        } finally {
            $this->assertDatabaseCount('idempotency_keys', 0); // no completed row, nothing replayable
        }
    }

    public function test_crash_before_response_is_recovered_by_reclaiming_a_stale_claim(): void // AC-11
    {
        // Simulate a process that claimed, then died before writing a response:
        // a claimed row whose lock is older than lock_timeout (60s).
        IdempotencyKey::create([
            'scope' => 'scope', 'key' => 'k1', 'request_fingerprint' => 'fp',
            'status' => IdempotencyStatus::Claimed->value,
            'locked_at' => now()->subMinutes(10), 'expires_at' => now()->addDay(),
        ]);

        [$fn, $calls] = $this->counter('recovered');
        $result = $this->service->remember('scope', 'k1', 'fp', $fn);

        $this->assertSame('recovered', $result);
        $this->assertSame(1, $calls(), 'stale claim must be reclaimed and run, never locked forever');
        $this->assertSame(IdempotencyStatus::Completed->value, IdempotencyKey::first()->status);
    }

    public function test_completed_entry_past_ttl_reruns_as_new(): void // AC-12
    {
        // A completed entry whose retention window has passed.
        IdempotencyKey::create([
            'scope' => 'scope', 'key' => 'k1', 'request_fingerprint' => 'fp',
            'status' => IdempotencyStatus::Completed->value,
            'response_snapshot' => ['value' => 'old'],
            'locked_at' => null, 'expires_at' => now()->subMinute(),
        ]);

        [$fn, $calls] = $this->counter('fresh');
        $result = $this->service->remember('scope', 'k1', 'fp', $fn);

        $this->assertSame('fresh', $result, 'hit after expiry re-runs as new');
        $this->assertSame(1, $calls());
    }

    public function test_loser_of_a_completed_claim_replays_not_double_writes(): void // AC-4 (deterministic loser path)
    {
        // First call completes and stores its response.
        [$fn, $calls] = $this->counter('once');
        $this->service->remember('scope', 'k1', 'fp', $fn);

        // A second concurrent caller (the "loser" — claim insert hits the unique
        // constraint) must replay, not produce a second effect.
        $second = $this->service->remember('scope', 'k1', 'fp', $fn);

        $this->assertSame('once', $second);
        $this->assertSame(1, $calls(), 'loser replays — exactly one effect');
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    public function test_null_and_array_results_round_trip(): void
    {
        $this->assertNull($this->service->remember('s', 'k-null', 'fp', fn () => null));
        $replayNull = $this->service->remember('s', 'k-null', 'fp', fn () => 'changed');
        $this->assertNull($replayNull, 'null result replays as null, not the new closure value');

        $arr = ['a' => 1, 'b' => [2, 3]];
        $this->assertSame($arr, $this->service->remember('s', 'k-arr', 'fp', fn () => $arr));
    }
}
