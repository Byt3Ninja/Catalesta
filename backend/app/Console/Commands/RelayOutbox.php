<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shared\Outbox\OutboxRelay;
use Illuminate\Console\Command;

/**
 * Drains the transactional outbox (Story 2.4). Runs on the queue-worker /
 * scheduler. `--once` does a single batch (used by tests); the default keeps
 * draining batches until the outbox is empty, then returns.
 */
final class RelayOutbox extends Command
{
    protected $signature = 'outbox:relay {--once : Process a single batch and exit} {--limit= : Override the batch size}';

    protected $description = 'Deliver undispatched outbox events to the consumer (at-least-once).';

    public function handle(OutboxRelay $relay): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $total = 0;

        do {
            $delivered = $relay->dispatchBatch($limit);
            $total += $delivered;
        } while (! $this->option('once') && $delivered > 0);

        $this->info("Delivered {$total} event(s).");

        return self::SUCCESS;
    }
}
