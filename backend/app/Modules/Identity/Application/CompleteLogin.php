<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use App\Modules\Identity\Domain\Exceptions\InvalidTokenException;
use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Domain\Models\LinkedIdentity;
use App\Modules\Identity\Domain\Models\LinkedIdentityToken;
use App\Shared\Audit\AuditLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CompleteLogin
{
    public function __construct(
        private readonly IdentityProvider $identityProvider,
        private readonly CaptureProfileSnapshot $captureProfileSnapshot,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Complete the OIDC authorization-code flow.
     *
     * Reads oidc.nonce and oidc.code_verifier from the session, exchanges the
     * code for tokens, validates the id_token, projects/upserts the Account,
     * stores encrypted tokens, captures an identity snapshot from the validated
     * claims, logs the user in via Sanctum SPA session, and audits auth.login.
     *
     * @throws AuthenticationException if the id_token is invalid / tampered
     */
    public function handle(string $code): Account
    {
        $nonce = (string) session('oidc.nonce', '');
        $verifier = (string) session('oidc.code_verifier', '');

        try {
            /** @var array<string,mixed> $tokens */
            $tokens = $this->identityProvider->exchangeCode($code, $verifier);
            $claims = $this->identityProvider->validateIdToken((string) $tokens['id_token'], $nonce);
        } catch (InvalidTokenException $e) {
            throw new AuthenticationException('Invalid token: '.$e->getMessage());
        }

        return DB::transaction(function () use ($tokens, $claims): Account {
            $link = LinkedIdentity::projectFromClaims($claims);
            $account = $link->account;

            LinkedIdentityToken::where('linked_identity_id', $link->id)->delete();

            $scopes = isset($tokens['scope'])
                ? array_values(array_filter(explode(' ', (string) $tokens['scope'])))
                : [];

            LinkedIdentityToken::create([
                'linked_identity_id' => $link->id,
                'access_token' => (string) $tokens['access_token'],
                'refresh_token' => isset($tokens['refresh_token']) ? (string) $tokens['refresh_token'] : null,
                'scopes' => $scopes,
                'expires_at' => now()->addSeconds((int) $tokens['expires_in']),
            ]);

            $this->captureProfileSnapshot->capture(
                $account,
                'identity',
                null,
                $claims,
                'profile.basic.read',
                $link->profile_version,
            );

            Auth::login($account);
            session()->regenerate();

            $this->auditLogger->record(
                'auth.login',
                'account',
                (string) $account->id,
                [],
                ['sub' => $link->subject_id],
            );

            return $account;
        });
    }
}
