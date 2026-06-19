<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->string('key');
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedInteger('order_index')->default(0);
            $t->timestampsTz();

            $t->unique(['program_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
