<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A scoring model — the version parent, program-scoped, org-scoped.
    // Immutable, content-addressed versions live in scoring_model_versions.
    public function up(): void
    {
        Schema::create('scoring_models', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->string('name');
            $t->ulid('current_published_version_id')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_models');
    }
};
