<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_stages', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->string('key');
            $t->string('name');
            $t->string('type');
            $t->unsignedInteger('order_index')->default(0);
            $t->string('parallel_group')->nullable();
            $t->ulid('current_published_version_id')->nullable();
            $t->timestampsTz();

            $t->unique(['program_id', 'key']);
            $t->index(['program_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_stages');
    }
};
