<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // An applicant's frozen submission (FR-030/031, AR-4). Tenant-owned. The
    // submission_snapshot jsonb is written once and never altered (the model
    // enforces write-once); it captures answers + content-addressed blob refs
    // (Story 2.1) + the resolved form/program/rubric version ids at submit time.
    public function up(): void
    {
        Schema::create('application_submissions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('organization_id')->index();   // server-set via BelongsToTenant
            $t->ulid('cohort_id')->index();
            $t->jsonb('submission_snapshot');
            $t->timestampTz('created_at')->useCurrent();

            $t->index(['organization_id', 'cohort_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_submissions');
    }
};
