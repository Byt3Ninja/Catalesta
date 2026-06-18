<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->string('name');
            $t->string('slug');
            $t->string('status')->default('draft');
            $t->text('description')->nullable();
            $t->jsonb('settings')->nullable();
            $t->ulid('template_id')->nullable()->index();
            $t->timestampsTz();

            $t->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
