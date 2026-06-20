<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use App\Shared\Idempotency\Exceptions\IdempotencyConflictException;
use App\Shared\Idempotency\Exceptions\IdempotencyInFlightException;
use App\Shared\Idempotency\IdempotencyService;
use App\Shared\Outbox\Contracts\OutboxConsumer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drains the outbox to the consumer at-least-once.
 *
 * Claim is a single guarded statement (Postgres adds FOR UPDATE SKIP LOCKED) so
 * two relays never double-claim a row — never a separate SELECT-then-UPDATE. A
 * row claimed but not delivered within the visibility timeout is reclaimed (crash
 * recovery). Delivery runs through IdempotencyService keyed on event id, so a
 * redelivered event has no second effect. Failures retry with exponential backoff
 * and dead-letter once attempts OR age exceeds the configured bound.
 *
 * Ordering: P1a makes NO ordering guarantee (claim is best-effort created_at
 * order); the consumer must be order-independent.
 */
final class OutboxRelay
{
    public function __construct(
        private readonly OutboxConsumer $consumer,
        private readonly IdempotencyService $idempotency,
    ) {}

    /** Claim and deliver one batch; returns the number successfully delivered. */
    public function dispatchBatch(?int $limit = null): int
    {
        $limit ??= (int) config('outbox.batch_size');
        $token = (string) Str::ulid();

        $claimed = $this->claim($token, $limit);
        $delivered = 0;

        foreach ($claimed as $event) {
            if ($this->deliver($event, $token)) {
                $delivered++;
            }
        }

        return $delivered;
    }

    /**
     * @return Collection<int, OutboxEvent>
     */
    private function claim(string $token, int $limit): mixed
    {
        $visibilityCutoff = now()->subSeconds((int) config('outbox.visibility_timeout_seconds'));

        $selectable = OutboxEvent::query()
            ->whereNull('dispatched_at')
            ->whereNull('dead_lettered_at')
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('claimed_at')->orWhere('claimed_at', '<', $visibilityCutoff))
            ->orderBy('created_at')
            ->limit($limit)
            ->select('id');

        // Postgres: lock + skip already-claimed rows for true multi-relay safety.
        // SQLite serializes writers, so the guarded single-statement UPDATE is
        // already race-free there.
        if (DB::connection()->getDriverName() === 'pgsql') {
            $selectable->lock('for update skip locked');
        }

        // Single atomic claim: UPDATE ... WHERE id IN (<selectable>).
        // claimed_at uses the app clock so it is compared against the same clock as
        // the visibility cutoff above (avoids a DB-vs-app skew that could reclaim a
        // freshly-claimed row early). dispatched_at stays DB-side (AC-6).
        // ponytail: single clock source; residual inter-host skew assumes NTP sync.
        OutboxEvent::whereIn('id', $selectable)->update([
            'claim_token' => $token,
            'claimed_at' => now(),
        ]);

        return OutboxEvent::where('claim_token', $token)->orderBy('created_at')->get();
    }

    private function deliver(OutboxEvent $event, string $token): bool
    {
        try {
            // Idempotent on event id — a redelivery replays (no second effect).
            $this->idempotency->remember(
                "outbox:{$this->consumer->name()}",
                $event->id,
                $event->id,
                fn () => $this->consumer->handle($event),
            );
        } catch (IdempotencyInFlightException) {
            // Another worker is delivering this same event right now (e.g. a
            // reclaim of a still-in-flight row). NOT a failure — release our claim
            // so the owner can finalize it; a later pass replays the completed
            // result and marks it dispatched. No attempt is burned.
            $this->release($event, $token);

            return false;
        } catch (IdempotencyConflictException $e) {
            // fingerprint == key for outbox, so a conflict means a corrupted or
            // colliding idempotency row — non-retryable. Dead-letter now and surface;
            // retrying would deterministically conflict until the attempt cap.
            Log::error('outbox.delivery_conflict', ['event_id' => $event->id, 'error' => $e->getMessage()]);
            $this->finalizeFailure($event, $token, $e, deadLetter: true);

            return false;
        } catch (Throwable $e) {
            $this->onFailure($event, $token, $e);

            return false;
        }

        // DB-side timestamp (AC-6). Guarded by our claim token so a zombie/reclaimed
        // worker cannot finalize a row another worker now owns.
        OutboxEvent::whereKey($event->id)
            ->where('claim_token', $token)
            ->update([
                'dispatched_at' => DB::raw('CURRENT_TIMESTAMP'),
                'claim_token' => null,
            ]);

        return true;
    }

    /** Drop our claim without penalty so the current owner can finish (in-flight). */
    private function release(OutboxEvent $event, string $token): void
    {
        OutboxEvent::whereKey($event->id)
            ->where('claim_token', $token)
            ->update(['claim_token' => null, 'claimed_at' => null]);
    }

    private function onFailure(OutboxEvent $event, string $token, Throwable $e): void
    {
        $this->finalizeFailure($event, $token, $e, deadLetter: $this->isPoison($event, $event->attempts + 1));
    }

    /** Token-guarded failure write: only the current owner updates retry/dead-letter state. */
    private function finalizeFailure(OutboxEvent $event, string $token, Throwable $e, bool $deadLetter): void
    {
        $attempts = $event->attempts + 1;

        $update = [
            'attempts' => $attempts,
            'last_error' => Str::limit($e->getMessage(), 1000, ''),
            'claim_token' => null,
            'claimed_at' => null,
            'available_at' => now()->addSeconds($this->backoff($attempts)),
        ];

        if ($deadLetter) {
            $update['dead_lettered_at'] = now();
        }

        OutboxEvent::whereKey($event->id)->where('claim_token', $token)->update($update);
    }

    private function isPoison(OutboxEvent $event, int $attempts): bool
    {
        $maxAttempts = (int) config('outbox.max_attempts');
        $maxAge = (int) config('outbox.max_age_seconds');
        $createdAt = $event->created_at instanceof Carbon ? $event->created_at : Carbon::parse((string) $event->created_at);

        return $attempts >= $maxAttempts || $createdAt->lt(now()->subSeconds($maxAge));
    }

    private function backoff(int $attempts): int
    {
        $base = (int) config('outbox.backoff_base_seconds');

        return $base ** $attempts;
    }
}
