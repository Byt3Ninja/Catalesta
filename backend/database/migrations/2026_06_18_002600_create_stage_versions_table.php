<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_versions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_stage_id')->index();
            $t->unsignedInteger('version_number')->default(0);
            $t->string('status')->default('draft');
            $t->jsonb('config')->nullable();
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();

            $t->unique(['program_stage_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_versions');
    }
};
