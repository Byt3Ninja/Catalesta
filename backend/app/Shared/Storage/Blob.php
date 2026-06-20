<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use Illuminate\Database\Eloquent\Model;

/**
 * A content-addressed blob. Primary key is the sha256 digest of the stored
 * content; identical content shares one row (see refcount).
 *
 * Intentionally NOT tenant-scoped — blobs are global by digest (AR-5 dedup).
 */
final class Blob extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'digest';

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'byte_size' => 'int',
        'refcount' => 'int',
        'created_at' => 'datetime',
    ];
}
