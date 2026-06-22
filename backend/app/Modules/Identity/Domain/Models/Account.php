<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Identity\Notifications\ResetPassword;
use App\Modules\Identity\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class Account extends Authenticatable implements MustVerifyEmail
{
    use HasUlids;
    use Notifiable;

    protected $guarded = [];

    /** @return array<string, string> */
    protected $casts = [
        'is_platform_admin' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /** @return HasMany<LinkedIdentity, $this> */
    public function linkedIdentities(): HasMany
    {
        return $this->hasMany(LinkedIdentity::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    /** @param string $token */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    /** The Startup-Gate `sub` for this account, if linked (null otherwise). */
    public function startupGateSubjectId(): ?string
    {
        return $this->linkedIdentities()
            ->where('provider', 'startup_gate')
            ->value('subject_id');
    }
}
