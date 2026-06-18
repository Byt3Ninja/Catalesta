<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class ExternalUser extends Authenticatable
{
    use HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_platform_admin' => 'boolean',
        'synchronized_at' => 'datetime',
        'profile_version' => 'integer',
    ];

    /**
     * Upsert an external user projection from OIDC claims.
     * The upsert key is ONLY startup_gate_subject_id (the immutable 'sub').
     * Email is never used as a lookup key.
     *
     * @param  array<string, mixed>  $claims
     */
    public static function projectFromClaims(array $claims): self
    {
        return self::updateOrCreate(
            ['startup_gate_subject_id' => $claims['sub']],
            [
                'email' => $claims['email'] ?? null,
                'display_name' => $claims['name'] ?? null,
                'avatar_url' => $claims['picture'] ?? null,
                'locale' => $claims['locale'] ?? null,
                'profile_version' => isset($claims['profile_updated_at'])
                    ? (int) $claims['profile_updated_at']
                    : 0,
                'synchronization_status' => 'synced',
                'synchronized_at' => now(),
            ],
        );
    }
}
