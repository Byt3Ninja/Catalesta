<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_role_requirements', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->string('role_key');
            $t->unsignedInteger('min_count')->default(0);
            $t->unsignedInteger('max_count')->nullable();
            $t->boolean('is_required')->default(true);
            $t->timestampsTz();

            $t->unique(['program_id', 'role_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_role_requirements');
    }
};
