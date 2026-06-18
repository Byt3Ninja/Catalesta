<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_transitions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->ulid('from_program_stage_id')->nullable()->index();
            $t->ulid('to_program_stage_id')->index();
            $t->jsonb('condition')->nullable();
            $t->unsignedInteger('order_index')->default(0);
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_transitions');
    }
};
