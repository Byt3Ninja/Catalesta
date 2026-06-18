<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_stage_statuses', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();
            $t->ulid('cohort_id')->index();
            $t->ulid('external_user_id')->index();
            $t->ulid('program_stage_id')->index();
            $t->string('status')->default('not_started');
            $t->timestampTz('entered_at')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->timestampsTz();

            $t->unique(['cohort_id', 'external_user_id', 'program_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_stage_statuses');
    }
};
