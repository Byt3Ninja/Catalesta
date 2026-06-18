<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_membership_roles', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_membership_id');
            $t->ulid('organization_role_id');
            $t->timestampsTz();

            $t->unique(['organization_membership_id', 'organization_role_id']);

            $t->foreign('organization_membership_id')
                ->references('id')
                ->on('organization_memberships')
                ->cascadeOnDelete();

            $t->foreign('organization_role_id')
                ->references('id')
                ->on('organization_roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_membership_roles');
    }
};
