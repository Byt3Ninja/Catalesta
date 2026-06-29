<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Forms become org-scoped reusable assets (program optional), and a form
    // carries one mutable draft version that has no content_hash until published.
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $t): void {
            $t->ulid('program_id')->nullable()->change();
        });

        Schema::table('form_versions', function (Blueprint $t): void {
            $t->string('content_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('form_versions', function (Blueprint $t): void {
            $t->string('content_hash', 64)->nullable(false)->change();
        });

        Schema::table('forms', function (Blueprint $t): void {
            $t->ulid('program_id')->nullable(false)->change();
        });
    }
};
