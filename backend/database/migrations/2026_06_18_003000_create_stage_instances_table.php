<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_instances', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('participant_stage_status_id')->index();
            $t->ulid('stage_version_id')->index();
            $t->timestampTz('started_at');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_instances');
    }
};
