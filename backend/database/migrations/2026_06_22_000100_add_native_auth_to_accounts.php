<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $t): void {
            $t->string('password')->nullable();
            $t->timestampTz('email_verified_at')->nullable();
            $t->unique('email');
        });

        // SG-linked accounts are trusted-verified (no-op on a fresh DB).
        DB::table('accounts')
            ->whereNull('email_verified_at')
            ->whereExists(function ($q): void {
                $q->select(DB::raw('1'))
                    ->from('linked_identities')
                    ->whereColumn('linked_identities.account_id', 'accounts.id')
                    ->where('linked_identities.provider', 'startup_gate');
            })
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $t): void {
            $t->dropUnique(['email']);
            $t->dropColumn(['password', 'email_verified_at']);
        });
    }
};
