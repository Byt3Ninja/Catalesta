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
            $t->ulid('account_id')->index();
            $t->string('context_type');
            $t->string('context_id')->nullable();
            $t->unsignedInteger('profile_version');
            $t->jsonb('payload_json');
            $t->string('consent_reference')->nullable();
            $t->char('hash', 64);
            $t->timestampTz('captured_at');

            $t->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_snapshots');
    }
};
