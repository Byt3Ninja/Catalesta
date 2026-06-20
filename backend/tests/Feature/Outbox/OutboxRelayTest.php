<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox;

use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Outbox\Contracts\OutboxConsumer;
use App\Shared\Outbox\OutboxEvent;
use App\Shared\Outbox\OutboxProducer;
use App\Shared\Outbox\OutboxRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** A spy consumer: counts deliveries and can be made to throw. */
final class SpyConsumer implements OutboxConsumer
{
    public int $calls = 0;

    public bool $shouldThrow = false;

    public function name(): string
    {
        return 'spy';
    }

    public function handle(OutboxEvent $event): void
    {
        $this->calls++;
        if ($this->shouldThrow) {
            throw new RuntimeException('consumer boom');
        }
    }
}

final class OutboxRelayTest extends TestCase
{
    use RefreshDatabase;

    private SpyConsumer $consumer;

    private OutboxProducer $producer;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'outbox.batch_size' => 100,
            'outbox.visibility_timeout_seconds' => 60,
            'outbox.max_attempts' => 6,
            'outbox.max_age_seconds' => 86400,
            'outbox.backoff_base_seconds' => 2,
            'idempotency.ttl_seconds' => 86400,
            'idempotency.lock_timeout_seconds' => 60,
            'idempotency.max_response_bytes' => 65536,
        ]);
        $this->consumer = new SpyConsumer;
        $this->app->instance(OutboxConsumer::class, $this->consumer);
        $this->producer = app(OutboxProducer::class);
    }

    private function relay(): OutboxRelay
    {
        return app(OutboxRelay::class);
    }

    private function seedEvents(int $n): void
    {
        DB::transaction(function () use ($n) {
            for ($i = 0; $i < $n; $i++) {
                $this->producer->record('order.placed', ['i' => $i]);
            }
        });
    }

    public function test_drains_all_undispatched_events(): void // AC-1
    {
        $this->seedEvents(3);

        $delivered = $this->relay()->dispatchBatch();

        $this->assertSame(3, $delivered);
        $this->assertSame(3, $this->consumer->calls);
        $this->assertSame(0, OutboxEvent::whereNull('dispatched_at')->count());
    }

    public function test_dispatched_events_are_not_redelivered_on_a_second_pass(): void // AC-1/4
    {
        $this->seedEvents(2);
        $this->relay()->dispatchBatch();
        $this->consumer->calls = 0;

        $delivered = $this->relay()->dispatchBatch(); // simulate a second relay run

        $this->assertSame(0, $delivered);
        $this->assertSame(0, $this->consumer->calls);
    }

    public function test_dispatched_at_is_set_by_the_db_clock_and_recent(): void // AC-6
    {
        $this->seedEvents(1);
        $this->relay()->dispatchBatch();

        $event = OutboxEvent::first();
        $this->assertNotNull($event->dispatched_at);
        $this->assertTrue($event->dispatched_at->diffInSeconds(now()) < 30, 'dispatched_at reflects DB now()');
    }

    public function test_redelivery_of_same_event_id_has_no_second_effect(): void // AC-2/8
    {
        $this->seedEvents(1);
        $this->relay()->dispatchBatch();
        $this->assertSame(1, $this->consumer->calls);

        // Force a redelivery: clear the dispatched marker so the row is claimable again.
        OutboxEvent::query()->update(['dispatched_at' => null, 'claim_token' => null, 'claimed_at' => null]);

        $this->relay()->dispatchBatch();

        $this->assertSame(1, $this->consumer->calls, 'idempotency on event_id blocks the second effect');
    }

    public function test_failure_increments_attempts_and_defers_with_backoff(): void // AC-3
    {
        $this->seedEvents(1);
        $this->consumer->shouldThrow = true;

        $delivered = $this->relay()->dispatchBatch();

        $this->assertSame(0, $delivered);
        $event = OutboxEvent::first();
        $this->assertSame(1, $event->attempts);
        $this->assertNull($event->dispatched_at);
        $this->assertNotNull($event->available_at);
        $this->assertTrue($event->available_at->isFuture(), 'backoff pushes the next attempt into the future');

        // Not re-claimed immediately (available_at in the future).
        $this->consumer->calls = 0;
        $this->assertSame(0, $this->relay()->dispatchBatch());
        $this->assertSame(0, $this->consumer->calls);
    }

    public function test_dead_letters_after_max_attempts(): void // AC-5 (attempts bound)
    {
        config(['outbox.max_attempts' => 1]);
        $this->seedEvents(1);
        $this->consumer->shouldThrow = true;

        $this->relay()->dispatchBatch(); // attempts 1 >= max 1 → dead-letter

        $event = OutboxEvent::first();
        $this->assertSame(1, $event->attempts);
        $this->assertNotNull($event->dead_lettered_at);

        // Dead-lettered rows are never claimed again.
        $this->consumer->calls = 0;
        $this->assertSame(0, $this->relay()->dispatchBatch());
        $this->assertSame(0, $this->consumer->calls);
    }

    public function test_dead_letters_after_max_age_even_below_attempt_cap(): void // AC-5 (age bound, the OR)
    {
        config(['outbox.max_attempts' => 99, 'outbox.max_age_seconds' => 3600]);
        $this->seedEvents(1);
        OutboxEvent::query()->update(['created_at' => now()->subHours(2)]); // older than max_age
        $this->consumer->shouldThrow = true;

        $this->relay()->dispatchBatch();

        $event = OutboxEvent::first();
        $this->assertSame(1, $event->attempts, 'well below the attempt cap');
        $this->assertNotNull($event->dead_lettered_at, 'dead-lettered on age alone (the OR bound)');
    }

    public function test_reclaims_a_row_whose_claim_went_stale_after_a_crash(): void // AC-7
    {
        $this->seedEvents(1);
        // Simulate a relay that claimed then crashed: claim_token set, claimed_at
        // older than the visibility timeout, dispatched_at still null.
        OutboxEvent::query()->update([
            'claim_token' => 'dead-worker',
            'claimed_at' => now()->subMinutes(10),
        ]);

        $delivered = $this->relay()->dispatchBatch();

        $this->assertSame(1, $delivered, 'a crashed claim is reclaimed and redelivered, not lost');
        $this->assertSame(1, $this->consumer->calls);
        $this->assertNotNull(OutboxEvent::first()->dispatched_at);
    }

    public function test_a_freshly_claimed_row_is_not_stolen_by_a_concurrent_pass(): void // AC-4 (claim invariant)
    {
        $this->seedEvents(1);
        // A row currently claimed by another relay (fresh claim, within timeout).
        OutboxEvent::query()->update([
            'claim_token' => 'other-relay',
            'claimed_at' => now(),
        ]);

        $delivered = $this->relay()->dispatchBatch();

        $this->assertSame(0, $delivered, 'a freshly-claimed row is not double-claimed');
        $this->assertSame(0, $this->consumer->calls);
    }

    public function test_in_flight_idempotency_is_not_counted_as_a_failure(): void // review fix 1
    {
        $this->seedEvents(1);
        $event = OutboxEvent::first();
        // Another worker is mid-delivery: a fresh, non-stale idempotency claim.
        IdempotencyKey::create([
            'scope' => 'outbox:spy', 'key' => $event->id, 'request_fingerprint' => $event->id,
            'status' => 'claimed', 'locked_at' => now(), 'expires_at' => now()->addDay(),
        ]);

        $delivered = $this->relay()->dispatchBatch();

        $this->assertSame(0, $delivered);
        $fresh = $event->fresh();
        $this->assertSame(0, $fresh->attempts, 'in-flight is not a failure — no attempt burned');
        $this->assertNull($fresh->dead_lettered_at, 'a healthy in-flight event is never dead-lettered');
        $this->assertNull($fresh->dispatched_at);
        $this->assertNull($fresh->claim_token, 'our claim is released so the owner can finalize');
    }

    public function test_idempotency_conflict_is_dead_lettered_immediately_not_retried(): void // review fix 3
    {
        $this->seedEvents(1);
        $event = OutboxEvent::first();
        // A corrupted/colliding idempotency row: completed under a DIFFERENT fingerprint.
        IdempotencyKey::create([
            'scope' => 'outbox:spy', 'key' => $event->id, 'request_fingerprint' => 'different-fingerprint',
            'status' => 'completed', 'response_snapshot' => ['value' => null],
            'locked_at' => null, 'expires_at' => now()->addDay(),
        ]);

        $delivered = $this->relay()->dispatchBatch();

        $this->assertSame(0, $delivered);
        $fresh = $event->fresh();
        $this->assertNotNull($fresh->dead_lettered_at, 'a hard conflict is non-retryable → dead-letter now');
        $this->assertNull($fresh->dispatched_at);
        $this->assertSame(0, $this->consumer->calls, 'consumer never ran (conflict precedes the closure)');
    }
}
