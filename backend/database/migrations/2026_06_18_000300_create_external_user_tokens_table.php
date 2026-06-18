<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_user_tokens', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('external_user_id')->index();
            $t->text('access_token');
            $t->text('refresh_token')->nullable();
            $t->jsonb('scopes')->nullable();
            $t->timestampTz('expires_at')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_user_tokens');
    }
};
