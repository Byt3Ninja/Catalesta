<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Consumers;

use App\Shared\Outbox\Contracts\OutboxConsumer;
use App\Shared\Outbox\OutboxEvent;
use Illuminate\Support\Facades\Log;

/**
 * The single P1a outbox consumer: a log/dev transport. Real notification
 * transports and multi-consumer fan-out are P2 (FR-100).
 */
final class LogOutboxConsumer implements OutboxConsumer
{
    public function name(): string
    {
        return 'log';
    }

    public function handle(OutboxEvent $event): void
    {
        Log::info('outbox.delivered', [
            'event_id' => $event->id,
            'event_type' => $event->event_type,
        ]);
    }
}
