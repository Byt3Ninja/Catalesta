<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use App\Modules\Identity\Domain\Exceptions\InvalidTokenException;
use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Identity\Domain\Models\ExternalUserToken;
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
     * code for tokens, validates the id_token, projects/upserts the ExternalUser,
     * stores encrypted tokens, captures an identity snapshot from the validated
     * claims, logs the user in via Sanctum SPA session, and audits auth.login.
     *
     * @throws AuthenticationException if the id_token is invalid / tampered
     */
    public function handle(string $code): ExternalUser
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

        return DB::transaction(function () use ($tokens, $claims): ExternalUser {
            $user = ExternalUser::projectFromClaims($claims);

            // Store / replace the user's external token record
            ExternalUserToken::where('external_user_id', $user->id)->delete();

            $scopes = isset($tokens['scope'])
                ? array_values(array_filter(explode(' ', (string) $tokens['scope'])))
                : [];

            ExternalUserToken::create([
                'external_user_id' => $user->id,
                'access_token' => (string) $tokens['access_token'],
                'refresh_token' => isset($tokens['refresh_token']) ? (string) $tokens['refresh_token'] : null,
                'scopes' => $scopes,
                'expires_at' => now()->addSeconds((int) $tokens['expires_in']),
            ]);

            // Capture immutable identity snapshot from validated claims only
            $this->captureProfileSnapshot->capture(
                $user,
                'identity',
                null,
                $claims,
                'profile.basic.read',
            );

            // Sanctum SPA session login
            Auth::login($user);
            session()->regenerate();

            $this->auditLogger->record(
                'auth.login',
                'external_user',
                (string) $user->id,
                [],
                ['sub' => $user->startup_gate_subject_id],
            );

            return $user;
        });
    }
}
