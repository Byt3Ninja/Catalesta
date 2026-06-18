<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_policies', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('program_id')->index();
            $t->string('key');
            $t->jsonb('value');
            $t->timestampsTz();

            $t->unique(['program_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_policies');
    }
};
