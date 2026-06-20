<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Transactional outbox (FR-050 / AR-3 / ADR-4). Domain events are written in
    // the SAME DB transaction as the state change, so an event can never be lost
    // or orphaned relative to its data. The relay (Story 2.4) drains undispatched
    // rows at-least-once; `id` is the event_id the consumer dedupes on.
    //
    // DELIBERATE: no organization_id — the substrate is generic; any tenant
    // context rides in `payload`. Ordering columns (aggregate_*) are a Story 2.4
    // concern and intentionally omitted here.
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $t) {
            $t->ulid('id')->primary();          // = event_id (consumer dedupe key, 2.4)
            $t->string('event_type');
            $t->jsonb('payload');
            $t->timestampTz('dispatched_at')->nullable()->index(); // null = undispatched
            $t->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
