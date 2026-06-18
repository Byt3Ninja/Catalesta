<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_id');
            $t->ulid('external_user_id');
            $t->string('status')->default('active');
            $t->timestampsTz();

            $t->unique(['organization_id', 'external_user_id']);
            $t->index('organization_id');
            $t->index('external_user_id');

            $t->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $t->foreign('external_user_id')
                ->references('id')
                ->on('external_users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
    }
};
