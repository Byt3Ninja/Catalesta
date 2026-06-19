<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_dependencies', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_stage_id')->index();           // dependent stage
            $t->ulid('depends_on_program_stage_id')->index(); // prerequisite
            $t->timestampsTz();
            $t->unique(['program_stage_id', 'depends_on_program_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_dependencies');
    }
};
