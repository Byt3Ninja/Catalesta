<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A durable idempotency claim. Identity is the UNIQUE(scope, key); the ulid id
 * is a surrogate so Eloquent can update/delete a single row. Consumer-agnostic —
 * intentionally not tenant-scoped (the scope string carries any tenancy).
 */
final class IdempotencyKey extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'response_snapshot' => 'array',
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
