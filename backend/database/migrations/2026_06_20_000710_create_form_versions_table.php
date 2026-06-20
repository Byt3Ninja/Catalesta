<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A published, immutable form version. content_hash is the content-addressed
    // version id (sha256 of the canonical definition) — the Epic 2 snapshot
    // contract (FR-020/012). UNIQUE(form_id, content_hash) makes an identical
    // republish idempotent (no duplicate version) and keeps the hash org-scoped.
    public function up(): void
    {
        Schema::create('form_versions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('form_id')->index();
            $t->unsignedInteger('version_number');
            $t->string('status'); // draft | published | archived
            $t->string('content_hash', 64);
            $t->jsonb('definition');
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();

            $t->unique(['form_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_versions');
    }
};
