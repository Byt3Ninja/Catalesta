<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_snapshots', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('external_user_id')->index();
            $t->string('context_type');
            $t->string('context_id')->nullable();
            $t->unsignedInteger('profile_version');
            $t->jsonb('payload_json');
            $t->string('consent_reference')->nullable();
            $t->char('hash', 64);
            $t->timestampTz('captured_at');

            $t->foreign('external_user_id')
                ->references('id')
                ->on('external_users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_snapshots');
    }
};
