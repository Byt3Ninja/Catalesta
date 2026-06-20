<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A durable domain event awaiting delivery. Written inside the producer's caller
 * transaction; drained at-least-once by the relay (Story 2.4). The ulid `id` is
 * the event_id consumers dedupe on. `dispatched_at` is null until delivered.
 */
final class OutboxEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'claimed_at' => 'datetime',
        'available_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'dead_lettered_at' => 'datetime',
        'attempts' => 'int',
        'created_at' => 'datetime',
    ];
}
