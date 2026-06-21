<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class Account extends Authenticatable
{
    use HasUlids;

    protected $guarded = [];

    /** @return array<string, string> */
    protected $casts = [
        'is_platform_admin' => 'boolean',
    ];

    /** @return HasMany<LinkedIdentity, $this> */
    public function linkedIdentities(): HasMany
    {
        return $this->hasMany(LinkedIdentity::class);
    }

    /** The Startup-Gate `sub` for this account, if linked (null otherwise). */
    public function startupGateSubjectId(): ?string
    {
        return $this->linkedIdentities()
            ->where('provider', 'startup_gate')
            ->value('subject_id');
    }
}
