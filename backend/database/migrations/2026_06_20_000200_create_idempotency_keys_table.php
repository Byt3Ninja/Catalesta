<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Durable idempotency claims (AR-2 / ADR-2). UNIQUE(scope, key) is the
    // concurrency guard: the first INSERT wins the claim, the loser replays.
    // Durable (DB, not redis TTL) so payment callbacks survive a cache flush.
    //
    // DELIBERATE: no organization_id — this is consumer-agnostic. Any tenancy the
    // caller wants rides inside the `scope` string. Do not "fix" by adding it.
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('scope');
            $t->string('key');
            $t->string('request_fingerprint');
            $t->jsonb('response_snapshot')->nullable();
            $t->string('status'); // claimed | completed
            $t->timestampTz('locked_at')->nullable();
            $t->timestampTz('expires_at')->nullable()->index();
            $t->timestampTz('created_at')->useCurrent();

            $t->unique(['scope', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
