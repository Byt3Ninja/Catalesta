<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use App\Modules\Identity\Domain\Contracts\ProfileProvider;
use App\Modules\Identity\Domain\Contracts\RoleProfileProvider;
use App\Modules\Identity\Domain\Contracts\StartupMembershipProvider;
use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Domain\Models\LinkedIdentityToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Provides /me endpoints for the authenticated user.
 *
 * GET /api/v1/me           — local projection (no external HTTP call).
 * GET /api/v1/me/profile   — passthrough to StartupGate profile API.
 * GET /api/v1/me/role-profiles — passthrough to StartupGate role-profiles API.
 * GET /api/v1/me/startups  — passthrough to StartupGate startups API.
 */
final class MeController extends Controller
{
    /**
     * GET /api/v1/me
     *
     * Returns the local projection of the authenticated user.
     * No outbound HTTP call is made — this is purely from the database projection.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Account $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'startup_gate_subject_id' => $user->startupGateSubjectId(),
                'email' => $user->email,
                'display_name' => $user->display_name,
                'avatar_url' => $user->avatar_url,
                'locale' => $user->locale,
                'email_verified' => $user->hasVerifiedEmail(),
                'linked_providers' => $user->linkedIdentities()->pluck('provider')->all(),
                'has_password' => $user->password !== null,
            ],
        ]);
    }

    /**
     * GET /api/v1/me/profile
     *
     * Fetches the general profile from StartupGate via the stored access token.
     * If the stored token is expired, a best-effort refresh is attempted first.
     */
    public function profile(
        Request $request,
        ProfileProvider $profileProvider,
        IdentityProvider $identityProvider,
    ): JsonResponse {
        $accessToken = $this->resolveAccessToken($request, $identityProvider);

        return response()->json($profileProvider->generalProfile($accessToken));
    }

    /**
     * GET /api/v1/me/role-profiles
     *
     * Passthrough to StartupGate role-profiles API.
     */
    public function roleProfiles(
        Request $request,
        RoleProfileProvider $roleProfileProvider,
        IdentityProvider $identityProvider,
    ): JsonResponse {
        $accessToken = $this->resolveAccessToken($request, $identityProvider);

        return response()->json($roleProfileProvider->roleProfiles($accessToken));
    }

    /**
     * GET /api/v1/me/startups
     *
     * Passthrough to StartupGate startups API.
     */
    public function startups(
        Request $request,
        StartupMembershipProvider $startupMembershipProvider,
        IdentityProvider $identityProvider,
    ): JsonResponse {
        $accessToken = $this->resolveAccessToken($request, $identityProvider);

        return response()->json($startupMembershipProvider->startups($accessToken));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves the current access token for the authenticated user.
     *
     * Fetches the latest LinkedIdentityToken row. If the token is expired, a
     * best-effort token refresh is attempted: on success the stored row is
     * rotated with the new credentials. On failure (e.g. no refresh token),
     * the existing token is used as-is.
     *
     * Returns the decrypted access token string.
     *
     * @throws AuthenticationException when no stored token exists.
     */
    private function resolveAccessToken(Request $request, IdentityProvider $identityProvider): string
    {
        /** @var Account $user */
        $user = $request->user();

        $link = $user->linkedIdentities()->where('provider', 'startup_gate')->first();

        /** @var LinkedIdentityToken|null $tokenRecord */
        $tokenRecord = $link
            ? LinkedIdentityToken::where('linked_identity_id', $link->id)->latest('created_at')->first()
            : null;

        if ($tokenRecord === null) {
            abort(401, 'No stored access token — re-authentication required.');
        }

        // Best-effort refresh if token is expired and we have a refresh token
        if ($tokenRecord->expires_at !== null
            && $tokenRecord->expires_at->isPast()
            && $tokenRecord->refresh_token !== null
        ) {
            try {
                $refreshed = $identityProvider->refresh($tokenRecord->refresh_token);

                $tokenRecord->update([
                    'access_token' => $refreshed['access_token'],
                    'refresh_token' => $refreshed['refresh_token'] ?? $tokenRecord->refresh_token,
                    'expires_at' => now()->addSeconds($refreshed['expires_in']),
                ]);
            } catch (\Throwable) {
                // Best-effort: proceed with existing token if refresh fails
            }
        }

        return $tokenRecord->access_token;
    }
}
