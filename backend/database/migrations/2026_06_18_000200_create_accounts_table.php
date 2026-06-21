<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('email')->nullable();
            $t->string('display_name')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('locale', 16)->nullable();
            $t->boolean('is_platform_admin')->default(false);
            $t->rememberToken();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
