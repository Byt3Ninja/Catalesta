<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_pipelines', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->unique(); // one pipeline per program
            $t->string('name');
            $t->ulid('current_published_version_id')->nullable();
            $t->timestampsTz();
        });

        Schema::create('stage_pipeline_versions', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('stage_pipeline_id')->index();
            $t->unsignedInteger('version_number');
            $t->string('status'); // draft | published | archived
            $t->string('content_hash', 64)->nullable();
            $t->jsonb('snapshot');
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();

            $t->unique(['stage_pipeline_id', 'content_hash']);
        });

        Schema::table('cohorts', function (Blueprint $t): void {
            $t->ulid('stage_pipeline_version_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $t): void {
            $t->dropColumn('stage_pipeline_version_id');
        });
        Schema::dropIfExists('stage_pipeline_versions');
        Schema::dropIfExists('stage_pipelines');
    }
};
