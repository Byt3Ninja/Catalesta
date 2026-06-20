<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use App\Shared\Idempotency\Exceptions\IdempotencyConflictException;
use App\Shared\Idempotency\Exceptions\IdempotencyInFlightException;
use App\Shared\Idempotency\Exceptions\ResponseTooLargeException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Consumer-agnostic idempotency: run an operation exactly once per (scope, key)
 * and replay its stored response on retry. Guards application submit (FR-032)
 * and, later, the payment callback (FR-072/073) — nothing in the signature is
 * specific to either. Durable (DB-backed), so replays survive a cache flush.
 */
final class IdempotencyService
{
    /**
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     *
     * @throws IdempotencyConflictException same key, different fingerprint (→422)
     * @throws IdempotencyInFlightException duplicate while first call runs (→409)
     * @throws ResponseTooLargeException response exceeds the configured cap
     */
    public function remember(string $scope, string $key, string $fingerprint, Closure $fn): mixed
    {
        // Claim-first: the UNIQUE(scope,key) insert is the cross-connection guard.
        if ($this->claim($scope, $key, $fingerprint)) {
            return $this->runOwned($scope, $key, $fn);
        }

        return $this->resolveExisting($scope, $key, $fingerprint, $fn);
    }

    private function claim(string $scope, string $key, string $fingerprint): bool
    {
        try {
            IdempotencyKey::create([
                'scope' => $scope,
                'key' => $key,
                'request_fingerprint' => $fingerprint,
                'status' => IdempotencyStatus::Claimed->value,
                'locked_at' => now(),
                'expires_at' => now()->addSeconds($this->ttl()),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function resolveExisting(string $scope, string $key, string $fingerprint, Closure $fn): mixed
    {
        $row = IdempotencyKey::where('scope', $scope)->where('key', $key)->first();

        // Lost between claim-failure and reload (rare race, e.g. a failed call
        // released the key) — retry the whole thing.
        if ($row === null) {
            return $this->remember($scope, $key, $fingerprint, $fn);
        }

        // Fingerprint mismatch always wins: never let a different request hijack
        // or replay another's key (AC-3, AC-7) — even on a stale claim.
        if ($row->request_fingerprint !== $fingerprint) {
            throw new IdempotencyConflictException($scope, $key);
        }

        if ($row->status === IdempotencyStatus::Claimed->value) {
            // Same-fingerprint retry of a crashed claim → reclaim and run (AC-11).
            if ($this->isStale($row) && $this->reclaim($row)) {
                return $this->runOwned($scope, $key, $fn);
            }
            // Otherwise the original call is genuinely still running (AC-8).
            throw new IdempotencyInFlightException($scope, $key);
        }

        // Completed but past its retention window → re-run as new (AC-12).
        if ($this->isExpired($row) && $this->reclaim($row)) {
            return $this->runOwned($scope, $key, $fn);
        }

        // Completed and fresh → replay (AC-2).
        return $this->decode($row->response_snapshot);
    }

    private function runOwned(string $scope, string $key, Closure $fn): mixed
    {
        try {
            $result = $fn();
        } catch (Throwable $e) {
            // Key-release on failure: a genuine retry can run again (AC-9).
            $this->release($scope, $key);
            throw $e;
        }

        $encoded = $this->encode($result);
        if (strlen($encoded) > $this->maxResponseBytes()) {
            // Fail closed — never store/replay a partial response (AC-10).
            $this->release($scope, $key);
            throw new ResponseTooLargeException($scope, $key);
        }

        IdempotencyKey::where('scope', $scope)->where('key', $key)->update([
            'response_snapshot' => $encoded,
            'status' => IdempotencyStatus::Completed->value,
            'locked_at' => null,
        ]);

        return $result;
    }

    /**
     * Take ownership of a stale/expired row. Optimistically guarded on the
     * observed locked_at so two concurrent reclaimers cannot both win.
     *
     * ponytail: optimistic single-column guard — sufficient at this scale; swap
     * for SELECT ... FOR UPDATE if reclaim contention ever becomes real.
     */
    private function reclaim(IdempotencyKey $row): bool
    {
        $affected = IdempotencyKey::where('id', $row->id)
            ->where('locked_at', $row->getRawOriginal('locked_at'))
            ->update([
                'status' => IdempotencyStatus::Claimed->value,
                'locked_at' => now(),
                'expires_at' => now()->addSeconds($this->ttl()),
                'response_snapshot' => null,
            ]);

        return $affected === 1;
    }

    private function release(string $scope, string $key): void
    {
        IdempotencyKey::where('scope', $scope)->where('key', $key)->delete();
    }

    private function isStale(IdempotencyKey $row): bool
    {
        $lockedAt = $row->locked_at;
        $staleLock = $lockedAt !== null && $lockedAt->lt(now()->subSeconds($this->lockTimeout()));

        return $staleLock || $this->isExpired($row);
    }

    private function isExpired(IdempotencyKey $row): bool
    {
        return $row->expires_at !== null && now()->greaterThan($row->expires_at);
    }

    /** Wrap the result so any value (scalar/array/null) round-trips through jsonb. */
    private function encode(mixed $result): string
    {
        return json_encode(['value' => $result], JSON_THROW_ON_ERROR);
    }

    private function decode(mixed $snapshot): mixed
    {
        $data = is_array($snapshot) ? $snapshot : json_decode((string) $snapshot, true);

        return $data['value'] ?? null;
    }

    private function ttl(): int
    {
        return (int) config('idempotency.ttl_seconds');
    }

    private function lockTimeout(): int
    {
        return (int) config('idempotency.lock_timeout_seconds');
    }

    private function maxResponseBytes(): int
    {
        return (int) config('idempotency.max_response_bytes');
    }
}
