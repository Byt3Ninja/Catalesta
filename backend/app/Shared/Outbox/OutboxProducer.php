<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

/**
 * The ONLY component allowed to emit a domain event — and it does so purely by
 * writing an outbox row (AR-7). Handlers never dispatch to a transport directly.
 *
 * CONTRACT: call record() from INSIDE the caller's DB::transaction, alongside the
 * domain write, so the event and the state change commit or roll back together
 * (FR-050). The producer deliberately does NOT open its own transaction — that
 * would split it from the domain write and defeat the atomicity guarantee. The
 * relay (Story 2.4) is what delivers; nothing is dispatched here.
 */
final class OutboxProducer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $eventType, array $payload): OutboxEvent
    {
        return OutboxEvent::create([
            'event_type' => $eventType,
            'payload' => $payload,
            'dispatched_at' => null,
        ]);
    }
}
