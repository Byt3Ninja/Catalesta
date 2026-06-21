<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Learning Telemetry event store (FR-080). Shaped like outbox_events (ulid id,
    // event_name, payload) but — unlike the generic outbox — carries an explicit
    // organization_id so events are tenant-scoped and queryable for the World-A/B
    // band. Append-only (next migration). High-volume, best-effort: NO actor
    // identity / IP is stored on public events (viewed/started are pre-auth) — keeps
    // telemetry out of PDPL/retention scope (NFR-013). organization_id is server-set
    // (BelongsToTenant); the (cohort_id, event_name) index serves the funnel counts.
    public function up(): void
    {
        Schema::create('learning_events', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index(); // server-set; tenant-queryable for the band
            $t->ulid('cohort_id')->index();
            $t->string('event_name'); // application.viewed | application.started | application.submitted | …
            $t->jsonb('payload')->nullable();
            $t->timestampTz('occurred_at');
            $t->timestampTz('created_at')->useCurrent();

            $t->index(['cohort_id', 'event_name']); // funnel aggregation
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_events');
    }
};
