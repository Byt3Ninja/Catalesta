<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_rules', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('stage_version_id')->index();
            $t->string('type'); // entry|exit
            $t->jsonb('expression');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_rules');
    }
};
