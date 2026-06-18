<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ProfileSnapshot extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'payload_json' => 'array',
        'captured_at' => 'datetime',
        'profile_version' => 'integer',
    ];

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new RuntimeException('Profile snapshots are immutable.');
        });
        self::deleting(function (): void {
            throw new RuntimeException('Profile snapshots are immutable.');
        });
    }
}
