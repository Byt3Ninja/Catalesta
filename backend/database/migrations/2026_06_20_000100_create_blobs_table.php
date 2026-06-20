<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Content-addressed blob registry (AR-5 / ADR-5). The natural key is the
    // sha256 digest of the file content, so identical content dedupes to one row.
    //
    // DELIBERATE: blobs are NOT tenant-owned and carry NO organization_id —
    // content addressing is global by digest, which is the whole point of dedup.
    // Tenant isolation is enforced at the *reference* layer (the submission
    // snapshot in Story 2.6), never on the shared, content-identical bytes.
    // Do not "fix" this by adding organization_id (would break AR-5 dedup).
    public function up(): void
    {
        Schema::create('blobs', function (Blueprint $t) {
            $t->string('digest', 64)->primary();       // sha256 hex
            $t->string('disk');                         // Flysystem disk name
            $t->string('path');                         // object key on the disk
            $t->unsignedBigInteger('byte_size');
            $t->unsignedInteger('refcount')->default(1);
            $t->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blobs');
    }
};
