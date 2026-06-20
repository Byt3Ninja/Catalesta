<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // An application form (the version parent), owned by a program, org-scoped.
    // Its published, immutable, content-addressed versions live in form_versions.
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $t) {
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
        Schema::dropIfExists('forms');
    }
};
