<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Relay-side columns (Story 2.4) added to the outbox table built in 2.3.
    // claim_token + claimed_at = the per-batch visibility lock; attempts/available_at
    // drive retry + backoff; dead_lettered_at is the terminal poison marker.
    // `available_at` is nullable (no DB default — SQLite can't ALTER-add a
    // non-constant default); the relay treats NULL as "immediately available".
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $t) {
            $t->string('claim_token')->nullable();
            $t->timestampTz('claimed_at')->nullable()->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestampTz('available_at')->nullable()->index();
            $t->text('last_error')->nullable();
            $t->timestampTz('dead_lettered_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $t) {
            $t->dropColumn(['claim_token', 'claimed_at', 'attempts', 'available_at', 'last_error', 'dead_lettered_at']);
        });
    }
};
