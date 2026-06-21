<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Modules\Identity\Application\CompleteLogin;
use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use App\Modules\Identity\Domain\Models\LinkedIdentityToken;
use App\Shared\Audit\AuditLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    /**
     * GET /api/v1/auth/login
     *
     * Generates PKCE state/nonce/verifier, stores them in session, and returns
     * the IdP authorization URL to which the client should redirect the user.
     *
     * Note: Use the session() helper (not $request->session()) so OIDC handshake state
     * persists whether or not Sanctum's stateful middleware injected StartSession
     * (it is skipped on requests without an Origin/Referer header).
     */
    public function login(IdentityProvider $provider): JsonResponse
    {
        $state = bin2hex(random_bytes(20));        // 40 hex chars
        $nonce = bin2hex(random_bytes(20));        // 40 hex chars
        $codeVerifier = bin2hex(random_bytes(32)); // 64 hex chars

        // S256 code_challenge = base64url(sha256(verifier, raw))
        $codeChallenge = rtrim(
            strtr(
                base64_encode(hash('sha256', $codeVerifier, true)),
                '+/',
                '-_',
            ),
            '=',
        );

        session(['oidc.state' => $state]);
        session(['oidc.nonce' => $nonce]);
        session(['oidc.code_verifier' => $codeVerifier]);

        /** @var array<int,string> $scopes */
        $scopes = config('identity.oidc.scopes', []);

        $authorizationUrl = $provider->buildAuthorizationUrl($state, $nonce, $codeChallenge, $scopes);

        return response()->json(['authorization_url' => $authorizationUrl]);
    }

    /**
     * POST /api/v1/auth/callback
     *
     * Validates state, delegates to CompleteLogin, and returns the user projection.
     */
    public function callback(Request $request, CompleteLogin $completeLogin): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        if ($validated['state'] !== session('oidc.state')) {
            throw new AuthenticationException('State mismatch — possible CSRF.');
        }

        $user = $completeLogin->handle($validated['code']);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'startup_gate_subject_id' => $user->startupGateSubjectId(),
                'email' => $user->email,
                'display_name' => $user->display_name,
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/session  (auth:sanctum)
     *
     * Returns the currently authenticated user.
     */
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'startup_gate_subject_id' => $user->startupGateSubjectId(),
                'email' => $user->email,
                'display_name' => $user->display_name,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout  (auth:sanctum)
     *
     * Revokes stored tokens best-effort, destroys the session, and audits the logout.
     */
    public function logout(Request $request, IdentityProvider $provider, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();

        $link = $user->linkedIdentities()->where('provider', 'startup_gate')->first();

        // Best-effort token revocation
        $tokens = $link
            ? LinkedIdentityToken::where('linked_identity_id', $link->id)->get()
            : collect();

        foreach ($tokens as $token) {
            try {
                $provider->revoke($token->access_token);
            } catch (\Throwable) {
                // Best-effort: swallow revocation failures
            }
        }

        if ($link) {
            LinkedIdentityToken::where('linked_identity_id', $link->id)->delete();
        }

        $audit->record('auth.logout', 'account', (string) $user->id);

        // Logout from session guard and invalidate session
        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        return response()->json(null, 204);
    }
}
