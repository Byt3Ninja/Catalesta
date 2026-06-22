<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class LinkedIdentity extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** @return array<string, string> */
    protected $casts = [
        'synchronized_at' => 'datetime',
        'linked_at' => 'datetime',
        'last_login_at' => 'datetime',
        'profile_version' => 'integer',
    ];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasOne<LinkedIdentityToken, $this> */
    public function token(): HasOne
    {
        return $this->hasOne(LinkedIdentityToken::class);
    }

    /**
     * Resolve-or-create the Startup-Gate identity link (and its Account) from OIDC claims.
     * Upsert key is (provider='startup_gate', subject_id=sub). Behavior-preserving:
     * the account's email/display_name/avatar/locale are refreshed from claims on every
     * login, exactly as the old ExternalUser projection did.
     *
     * @param  array<string,mixed>  $claims
     */
    public static function projectFromClaims(array $claims): self
    {
        $link = self::firstOrNew([
            'provider' => 'startup_gate',
            'subject_id' => (string) $claims['sub'],
        ]);

        $account = $link->exists ? $link->account : new Account;
        $account->fill([
            // Normalize email lowercase on every write path (matches native register/login)
            // so the unique index + credential lookups are case-insensitive.
            'email' => isset($claims['email']) ? strtolower(trim((string) $claims['email'])) : null,
            'display_name' => $claims['name'] ?? null,
            'avatar_url' => $claims['picture'] ?? null,
            'locale' => $claims['locale'] ?? null,
        ]);
        if ($account->email_verified_at === null) {
            $account->email_verified_at = now(); // SG email is trusted
        }
        $account->save();

        if (! $link->exists) {
            $link->account()->associate($account);
            $link->linked_at = now();
        }

        $link->fill([
            'display_name' => $claims['name'] ?? null,
            'avatar_url' => $claims['picture'] ?? null,
            'locale' => $claims['locale'] ?? null,
            'profile_version' => isset($claims['profile_updated_at']) ? (int) $claims['profile_updated_at'] : 0,
            'synchronization_status' => 'synced',
            'synchronized_at' => now(),
            'last_login_at' => now(),
        ]);
        $link->save();

        return $link;
    }
}
