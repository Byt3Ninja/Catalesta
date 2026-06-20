<?php

declare(strict_types=1);

return [
    // Max events claimed + delivered per relay batch.
    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),

    // A claimed-but-undispatched row older than this is reclaimable (a relay that
    // crashed mid-batch). Keeps rows from being locked forever (Story 2.4 AC-7).
    // MUST be > idempotency.lock_timeout_seconds: by the time the outbox reclaims a
    // row, its idempotency claim should already be stale so the reclaiming worker
    // re-runs cleanly instead of hitting an in-flight conflict at the boundary.
    'visibility_timeout_seconds' => (int) env('OUTBOX_VISIBILITY_TIMEOUT_SECONDS', 120),

    // Poison bounds — a failing event is dead-lettered when EITHER is exceeded.
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 6),       // FR-050 cap
    'max_age_seconds' => (int) env('OUTBOX_MAX_AGE_SECONDS', 86400),

    // Exponential backoff base; next attempt waits backoff_base ** attempts secs.
    'backoff_base_seconds' => (int) env('OUTBOX_BACKOFF_BASE_SECONDS', 2),
];
