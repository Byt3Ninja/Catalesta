<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The published form version attached to a cohort when it opens (Story 1.4).
    // Nullable: a draft cohort has none until opened with an attached form.
    public function up(): void
    {
        Schema::table('cohorts', function (Blueprint $t) {
            $t->ulid('form_version_id')->nullable()->after('program_id');
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $t) {
            $t->dropColumn('form_version_id');
        });
    }
};
