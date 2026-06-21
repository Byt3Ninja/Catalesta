<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A published, immutable program version (FR-010/012). Mirrors stage_versions:
    // sequenced by version_number within a program (no content_hash — that is the
    // form snapshot contract). organization_id is server-set (BelongsToTenant) and
    // carries an explicit cross-tenant isolation test (AR-6).
    public function up(): void
    {
        Schema::create('program_versions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->unsignedInteger('version_number')->default(0);
            $t->string('status')->default('draft'); // draft | published | archived
            $t->jsonb('definition');
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();

            $t->unique(['program_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_versions');
    }
};
