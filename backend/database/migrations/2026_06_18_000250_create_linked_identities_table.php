<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_identities', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('account_id');
            $t->string('provider');
            $t->string('subject_id');
            $t->string('display_name')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('locale', 16)->nullable();
            $t->unsignedBigInteger('profile_version')->default(0);
            $t->string('synchronization_status')->default('pending');
            $t->timestampTz('synchronized_at')->nullable();
            $t->timestampTz('linked_at')->nullable();
            $t->timestampTz('last_login_at')->nullable();
            $t->timestampsTz();

            $t->unique(['provider', 'subject_id']);
            $t->unique(['account_id', 'provider']);
            $t->index('account_id');

            $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linked_identities');
    }
};
