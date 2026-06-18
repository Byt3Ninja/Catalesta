<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\StartupGateMock\Support\MockKeys;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestRsa;
use Tests\TestCase;

/**
 * Mandatory OIDC + consent security tests (docs/12).
 *
 * Every bad-token variant asserts 401. If any assertion here is relaxed the test
 * must be treated as a security regression, not a testing convenience.
 *
 * Revoked-consent enforcement lives inside the mock Startup Gate profile API
 * (contract-tested in tests/Contract/ProfileApiContractTest) — this suite
 * avoids duplicating that internal mock logic. Instead it adds a focused
 * cross-check that the platform passthrough relays whatever the consent-filtered
 * profile API returns (an empty/gated payload for sg_user_08).
 */
final class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Drives GET /api/v1/auth/login and returns [state, nonce] from the session.
     *
     * @return array{state: string, nonce: string}
     */
    private function initiateLogin(): array
    {
        $this->getJson('/api/v1/auth/login')->assertOk();

        $state = session('oidc.state');
        $nonce = session('oidc.nonce');

        $this->assertNotEmpty($state, 'Session must contain oidc.state after /login');
        $this->assertNotEmpty($nonce, 'Session must contain oidc.nonce after /login');

        return ['state' => (string) $state, 'nonce' => (string) $nonce];
    }

    /**
     * Returns the trimmed OIDC issuer base URL (no trailing slash).
     */
    private function issuer(): string
    {
        return rtrim((string) config('identity.oidc.issuer'), '/');
    }

    /**
     * Fakes the token endpoint to return the given id_token and fakes JWKS
     * to return MockKeys public key (correct key, so only bad claims cause rejection).
     */
    private function fakeWithToken(string $idToken): void
    {
        Http::fake([
            $this->issuer().'/oauth/token' => Http::response([
                'id_token' => $idToken,
                'access_token' => 'at',
                'refresh_token' => 'rt',
                'expires_in' => 3600,
                'scope' => 'openid profile.basic.read',
            ]),
            $this->issuer().'/.well-known/jwks.json' => Http::response(MockKeys::jwks()),
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. Expired token rejected → 401
    // -------------------------------------------------------------------------

    public function test_expired_token_rejected_with_401(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->initiateLogin();

        $idToken = JWT::encode(
            [
                'iss' => config('identity.oidc.issuer'),
                'aud' => config('identity.oidc.client_id'),
                'sub' => 'sg_user_01',
                'nonce' => $nonce,
                'iat' => time() - 7200,
                'exp' => time() - 3600, // expired one hour ago
            ],
            MockKeys::privateKeyPem(),
            'RS256',
            MockKeys::kid(),
        );

        $this->fakeWithToken($idToken);

        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => $state])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 2. Invalid issuer rejected → 401
    // -------------------------------------------------------------------------

    public function test_invalid_issuer_rejected_with_401(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->initiateLogin();

        $idToken = JWT::encode(
            [
                'iss' => 'https://evil.example', // wrong issuer
                'aud' => config('identity.oidc.client_id'),
                'sub' => 'sg_user_01',
                'nonce' => $nonce,
                'iat' => time(),
                'exp' => time() + 3600,
            ],
            MockKeys::privateKeyPem(),
            'RS256',
            MockKeys::kid(),
        );

        $this->fakeWithToken($idToken);

        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => $state])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 3. Invalid audience rejected → 401
    // -------------------------------------------------------------------------

    public function test_invalid_audience_rejected_with_401(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->initiateLogin();

        $idToken = JWT::encode(
            [
                'iss' => config('identity.oidc.issuer'),
                'aud' => 'wrong-client', // wrong audience
                'sub' => 'sg_user_01',
                'nonce' => $nonce,
                'iat' => time(),
                'exp' => time() + 3600,
            ],
            MockKeys::privateKeyPem(),
            'RS256',
            MockKeys::kid(),
        );

        $this->fakeWithToken($idToken);

        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => $state])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 4. Tampered / wrong-signature token rejected → 401
    // -------------------------------------------------------------------------

    public function test_wrong_signature_token_rejected_with_401(): void
    {
        ['state' => $state, 'nonce' => $nonce] = $this->initiateLogin();

        // Sign with a throwaway key while advertising the same kid as MockKeys.
        // JWKS will return MockKeys' public key → signature verification fails.
        $wrongKey = TestRsa::generate();

        $idToken = JWT::encode(
            [
                'iss' => config('identity.oidc.issuer'),
                'aud' => config('identity.oidc.client_id'),
                'sub' => 'sg_user_01',
                'nonce' => $nonce,
                'iat' => time(),
                'exp' => time() + 3600,
            ],
            $wrongKey['private'],
            'RS256',
            'sg-mock-key-1', // same kid but the signing key is different
        );

        // JWKS returns MockKeys' public key — mismatch with throwaway private key
        Http::fake([
            $this->issuer().'/oauth/token' => Http::response([
                'id_token' => $idToken,
                'access_token' => 'at',
                'refresh_token' => 'rt',
                'expires_in' => 3600,
            ]),
            $this->issuer().'/.well-known/jwks.json' => Http::response(MockKeys::jwks()),
        ]);

        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => $state])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 5. Tampered state rejected → 401
    // -------------------------------------------------------------------------

    public function test_tampered_state_rejected_with_401(): void
    {
        $this->getJson('/api/v1/auth/login')->assertOk();

        // Deliberately POST with a state value that was never issued by /login
        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => 'tampered-state-value'])
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 6. Unauthenticated /me → 401
    // -------------------------------------------------------------------------

    public function test_unauthenticated_me_returns_401(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // 7. Revoked-consent passthrough — real platform relay assertion
    //
    // Consent enforcement lives inside the mock Startup Gate profile API and is
    // contract-tested exhaustively in ProfileApiContractTest. This test proves
    // the PLATFORM is a faithful passthrough: it relays exactly what the upstream
    // profile API returns, without fabricating or injecting gated fields.
    //
    // The platform uses session-based Sanctum auth (Auth::login in CompleteLogin),
    // so the same test client retains the session cookie after callback — no
    // separate token field is returned or needed.
    // -------------------------------------------------------------------------

    public function test_platform_relays_consent_filtered_profile_response(): void
    {
        // Step 1 — complete OIDC login for the revoked-consent persona (sg_user_08).
        ['state' => $state, 'nonce' => $nonce] = $this->initiateLogin();

        $idToken = JWT::encode(
            [
                'iss' => config('identity.oidc.issuer'),
                'aud' => config('identity.oidc.client_id'),
                'sub' => 'sg_user_08', // revoked-consent persona
                'email' => 'revoked@example.com',
                'name' => 'Revoked User',
                'locale' => 'en',
                'profile_updated_at' => 1781712000,
                'nonce' => $nonce,
                'iat' => time(),
                'exp' => time() + 3600,
            ],
            MockKeys::privateKeyPem(),
            'RS256',
            MockKeys::kid(),
        );

        $issuer = $this->issuer();

        // Step 2 — fake upstream profile API to return a realistic consent-gated
        // payload for sg_user_08: only the immutable 'sub' claim is present;
        // gated fields (bio, avatar_url, location) are ABSENT (not null, but missing),
        // which is exactly what the mock returns when consent is revoked.
        $gatedProfilePayload = ['sub' => 'sg_user_08'];

        Http::fake([
            $issuer.'/oauth/token' => Http::response([
                'id_token' => $idToken,
                'access_token' => 'at-sg-user-08',
                'refresh_token' => 'rt',
                'expires_in' => 3600,
                'scope' => 'openid',
            ]),
            $issuer.'/.well-known/jwks.json' => Http::response(MockKeys::jwks()),
            // Profile API returns consent-gated payload — no bio/avatar_url/location
            config('identity.profile_api_base_url').'/me/profile' => Http::response($gatedProfilePayload, 200),
        ]);

        // Login succeeds (200) and the test session becomes authenticated.
        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => $state])
            ->assertOk();

        // Step 3 — call /api/v1/me/profile using the retained session (no token needed).
        // Assert the platform relays the gated payload faithfully and does NOT inject
        // any fabricated fields that were absent from the upstream response.
        $this->getJson('/api/v1/me/profile')
            ->assertOk()
            ->assertExactJson($gatedProfilePayload);
    }
}
