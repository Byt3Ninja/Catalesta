<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->string('name');
            $t->string('slug');
            $t->string('status')->default('draft');
            $t->timestampTz('enrollment_opens_at')->nullable();
            $t->timestampTz('enrollment_closes_at')->nullable();
            $t->timestampTz('starts_at')->nullable();
            $t->timestampTz('ends_at')->nullable();
            $t->unsignedInteger('capacity')->nullable();
            $t->jsonb('timeline')->nullable();
            $t->timestampsTz();

            $t->unique(['program_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohorts');
    }
};
