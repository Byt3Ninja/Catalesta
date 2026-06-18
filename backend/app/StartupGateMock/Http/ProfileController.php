<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use App\StartupGateMock\Support\SeedPersonas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Mock profile API endpoints.
 *
 * All actions resolve the authenticated persona from the Bearer access token
 * via the cache key 'oidc_access:{token}' populated by TokenController.
 * Returns 401 if the token is absent or unknown.
 *
 * Gated profile sections (bio, avatar_url, location) are only returned when
 * the persona's token scopes include 'profile.basic.read' AND the persona
 * does not have consent_revoked=true.
 */
final class ProfileController extends Controller
{
    /**
     * Gated profile scopes — presence of any of these (with consent not revoked)
     * unlocks the full profile sections.
     *
     * @var list<string>
     */
    private const GATED_SCOPES = [
        'profile.basic.read',
        'profile.founder.read',
        'profile.mentor.read',
        'profile.professional.read',
        'profile.service_provider.read',
    ];

    /**
     * GET /api/v1/me
     * Returns basic identity: sub, email, email_verified, name, locale.
     */
    public function me(Request $request): JsonResponse
    {
        [$persona] = $this->resolvePersona($request);

        if ($persona === null) {
            return $this->unauthorized();
        }

        return response()->json([
            'sub' => $persona['sub'],
            'email' => $persona['email'],
            'email_verified' => $persona['email_verified'],
            'name' => $persona['name'],
            'locale' => $persona['locale'],
        ]);
    }

    /**
     * GET /api/v1/me/profile
     * Returns profile payload, consent-aware.
     * If consent_revoked or gated scope absent → omit gated sections.
     */
    public function profile(Request $request): JsonResponse
    {
        [$persona, $scopes] = $this->resolvePersona($request);

        if ($persona === null) {
            return $this->unauthorized();
        }

        if ($this->hasGatedAccess($persona, $scopes)) {
            return response()->json($persona['profile']);
        }

        // No gated access: return empty profile (no fabricated data)
        return response()->json([]);
    }

    /**
     * GET /api/v1/me/role-profiles
     * Returns role profiles.
     * For expired-role-verification personas, the expired entry is returned as-is
     * (verified=false, expired_at present) so the consumer can detect the expiry.
     */
    public function roleProfiles(Request $request): JsonResponse
    {
        [$persona] = $this->resolvePersona($request);

        if ($persona === null) {
            return $this->unauthorized();
        }

        return response()->json($persona['role_profiles']);
    }

    /**
     * GET /api/v1/me/startups
     * Returns the persona's startups.
     */
    public function startups(Request $request): JsonResponse
    {
        [$persona] = $this->resolvePersona($request);

        if ($persona === null) {
            return $this->unauthorized();
        }

        return response()->json($persona['startups']);
    }

    /**
     * GET /api/v1/me/consents
     * Returns [{scope, granted, reference}, ...].
     * For revoked-consent personas, all gated scopes are reported as granted:false.
     */
    public function consents(Request $request): JsonResponse
    {
        [$persona] = $this->resolvePersona($request);

        if ($persona === null) {
            return $this->unauthorized();
        }

        /** @var bool $revoked */
        $revoked = (bool) ($persona['consent_revoked'] ?? false);
        /** @var list<array<string,mixed>> $consentRecords */
        $consentRecords = (array) ($persona['consents'] ?? []);

        if ($revoked) {
            // Return all previously-known consents as granted:false,
            // or if there are none, return the gated scopes as revoked entries.
            if ($consentRecords !== []) {
                $result = array_map(
                    static fn (array $c): array => [
                        'scope' => (string) $c['scope'],
                        'granted' => false,
                        'reference' => null,
                    ],
                    $consentRecords
                );
            } else {
                // Emit one entry per gated scope known to be revoked
                $result = array_map(
                    static fn (string $scope): array => [
                        'scope' => $scope,
                        'granted' => false,
                        'reference' => null,
                    ],
                    self::GATED_SCOPES
                );
            }

            return response()->json($result);
        }

        // Normal path: map each consent to the contract shape
        $result = array_map(
            static fn (array $c): array => [
                'scope' => (string) $c['scope'],
                'granted' => true,
                'reference' => $c['granted_at'] ?? null,
            ],
            $consentRecords
        );

        return response()->json($result);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves the persona from the Bearer access token.
     *
     * @return array{0: array<string,mixed>|null, 1: list<string>}
     */
    private function resolvePersona(Request $request): array
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return [null, []];
        }

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get('oidc_access:'.$token);

        if ($cached === null) {
            return [null, []];
        }

        $sub = (string) ($cached['sub'] ?? '');
        /** @var list<string> $scopes */
        $scopes = (array) ($cached['scopes'] ?? []);

        $persona = SeedPersonas::find($sub);

        return [$persona, $scopes];
    }

    private function extractBearerToken(Request $request): ?string
    {
        $token = $request->bearerToken();

        return ($token !== null && $token !== '') ? $token : null;
    }

    /**
     * Returns true if the persona has not revoked consent AND the token scopes
     * include at least one gated scope.
     *
     * @param  array<string,mixed>  $persona
     * @param  list<string>  $scopes
     */
    private function hasGatedAccess(array $persona, array $scopes): bool
    {
        if ((bool) ($persona['consent_revoked'] ?? false)) {
            return false;
        }

        foreach (self::GATED_SCOPES as $gated) {
            if (in_array($gated, $scopes, true)) {
                return true;
            }
        }

        return false;
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['error' => 'unauthorized', 'error_description' => 'Valid Bearer token required.'], 401);
    }
}
