<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A published, immutable scoring-model version. content_hash is the
    // content-addressed version id (sha256 of canonical criteria JSON).
    // UNIQUE(scoring_model_id, content_hash) makes identical republish idempotent.
    // content_hash is nullable until publish (draft may exist without a hash).
    public function up(): void
    {
        Schema::create('scoring_model_versions', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('scoring_model_id')->index();
            $t->unsignedInteger('version_number');
            $t->string('status');        // draft | published
            $t->string('content_hash', 64)->nullable();
            $t->jsonb('criteria');       // array of {criterion_id, label, max_points, descriptors}
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();

            $t->unique(['scoring_model_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_model_versions');
    }
};
