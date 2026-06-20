<?php

declare(strict_types=1);

return [
    // How long a completed result stays replayable. A hit after this window
    // re-runs as new (exactly-once holds only within the window — see AC-12).
    'ttl_seconds' => (int) env('IDEMPOTENCY_TTL_SECONDS', 86400),

    // A claim whose lock is older than this is considered stale and may be
    // reclaimed (recovers from a crash between claim and response — AC-11).
    'lock_timeout_seconds' => (int) env('IDEMPOTENCY_LOCK_TIMEOUT_SECONDS', 60),

    // Max serialized response size. Oversize fails closed (never truncate-and-
    // replay a partial response — AC-10). Default 64 KiB.
    'max_response_bytes' => (int) env('IDEMPOTENCY_MAX_RESPONSE_BYTES', 65536),
];
