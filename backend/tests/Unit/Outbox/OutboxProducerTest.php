<?php

declare(strict_types=1);

namespace Tests\Unit\Outbox;

use App\Shared\Idempotency\IdempotencyKey;
use App\Shared\Outbox\OutboxEvent;
use App\Shared\Outbox\OutboxProducer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class OutboxProducerTest extends TestCase
{
    use RefreshDatabase;

    private OutboxProducer $producer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->producer = app(OutboxProducer::class);
    }

    public function test_records_an_undispatched_event_with_payload_and_ulid_id(): void // AC-1, AC-2
    {
        $event = DB::transaction(fn () => $this->producer->record('order.placed', ['order' => 42]));

        $this->assertDatabaseCount('outbox_events', 1);
        $stored = OutboxEvent::first();
        $this->assertSame('order.placed', $stored->event_type);
        $this->assertSame(['order' => 42], $stored->payload);
        $this->assertNull($stored->dispatched_at, 'a freshly recorded event is undispatched');
        $this->assertTrue(Str::isUlid($stored->id), 'id must be a ULID (the 2.4 consumer dedupe key)');
        $this->assertSame($stored->id, $event->id);
    }

    public function test_rollback_leaves_no_orphan_event_and_no_domain_row(): void // ★ AC-5
    {
        // A domain write (stand-in: an idempotency_keys row) AND the outbox event
        // in one transaction that then aborts. Neither may survive.
        try {
            DB::transaction(function () {
                IdempotencyKey::create([
                    'scope' => 'orders', 'key' => 'k1', 'request_fingerprint' => 'fp',
                    'status' => 'claimed', 'locked_at' => now(), 'expires_at' => now()->addDay(),
                ]);
                $this->producer->record('order.placed', ['order' => 42]);

                throw new RuntimeException('domain failure after recording the event');
            });
            $this->fail('expected the transaction to abort');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('outbox_events', 0); // no orphan event
        $this->assertDatabaseCount('idempotency_keys', 0); // no orphan data
    }

    public function test_committed_event_persists(): void // AC-2 (the commit side of the invariant)
    {
        DB::transaction(function () {
            $this->producer->record('order.placed', ['order' => 1]);
        });

        $this->assertDatabaseCount('outbox_events', 1);
    }

    public function test_producer_dispatches_to_no_transport_only_writes_a_row(): void // AC-3 tripwire
    {
        Bus::fake();
        Queue::fake();

        $this->producer->record('order.placed', ['order' => 7]);

        Bus::assertNothingDispatched();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('outbox_events', 1);
    }
}
