<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission_assignments', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('organization_role_id');
            $t->ulid('organization_permission_id');
            $t->timestampsTz();

            $t->unique(['organization_role_id', 'organization_permission_id']);

            $t->foreign('organization_role_id')
                ->references('id')
                ->on('organization_roles')
                ->cascadeOnDelete();

            $t->foreign('organization_permission_id')
                ->references('id')
                ->on('organization_permissions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_assignments');
    }
};
