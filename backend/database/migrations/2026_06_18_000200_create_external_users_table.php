<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_users', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('startup_gate_subject_id')->unique();   // immutable 'sub'
            $t->string('email')->nullable();                   // NOT a linkage key
            $t->string('display_name')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('locale', 16)->nullable();
            $t->unsignedBigInteger('profile_version')->default(0);
            $t->string('synchronization_status')->default('pending');
            $t->timestampTz('synchronized_at')->nullable();
            $t->boolean('is_platform_admin')->default(false);
            $t->rememberToken();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_users');
    }
};
