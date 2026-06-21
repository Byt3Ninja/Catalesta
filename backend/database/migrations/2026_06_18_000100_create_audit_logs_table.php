<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->ulid('actor_account_id')->nullable()->index();
            $t->ulid('organization_id')->nullable()->index();
            $t->string('action');
            $t->string('target_type')->nullable();
            $t->string('target_id')->nullable();
            $t->jsonb('before')->nullable();
            $t->jsonb('after')->nullable();
            $t->string('ip_address')->nullable();
            $t->string('correlation_id')->nullable();
            $t->string('result')->default('success');
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
