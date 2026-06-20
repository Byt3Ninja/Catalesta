<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Contracts;

use App\Shared\Outbox\OutboxEvent;

/**
 * Receives delivered outbox events. The relay invokes handle() through the
 * idempotency service keyed on event id, so handle() must only be correct for a
 * single delivery — the relay guarantees at-most-once *effect* per event_id.
 */
interface OutboxConsumer
{
    /** Stable name; used as the idempotency scope (`outbox:{name}`). */
    public function name(): string;

    public function handle(OutboxEvent $event): void;
}
