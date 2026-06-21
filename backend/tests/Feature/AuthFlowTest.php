<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\StartupGateMock\Support\MockKeys;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestRsa;
use Tests\TestCase;

final class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_logs_in_through_oidc_and_projection_uses_sub(): void
    {
        // Step 1: GET /api/v1/auth/login — stores state/nonce/verifier in session
        $login = $this->getJson('/api/v1/auth/login')->assertOk()->json();
        $this->assertArrayHasKey('authorization_url', $login);

        // Read state + nonce from session (test client persists session across calls)
        $state = session('oidc.state');
        $nonce = session('oidc.nonce');
        $this->assertNotEmpty($state);
        $this->assertNotEmpty($nonce);

        // Build a valid id_token signed with MockKeys
        $idToken = JWT::encode(
            [
                'iss' => config('identity.oidc.issuer'),
                'aud' => config('identity.oidc.client_id'),
                'sub' => 'sg_user_01',
                'email' => 'founder@example.com',
                'name' => 'Mock Founder',
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

        $issuer = rtrim((string) config('identity.oidc.issuer'), '/');

        Http::fake([
            $issuer.'/oauth/token' => Http::response([
                'id_token' => $idToken,
                'access_token' => 'at',
                'refresh_token' => 'rt',
                'expires_in' => 3600,
                'scope' => 'openid profile.basic.read',
            ]),
            $issuer.'/.well-known/jwks.json' => Http::response(MockKeys::jwks()),
        ]);

        // Step 2: POST /api/v1/auth/callback — complete login
        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => $state])
            ->assertOk()
            ->assertJsonPath('user.startup_gate_subject_id', 'sg_user_01');

        $this->assertDatabaseHas('linked_identities', ['provider' => 'startup_gate', 'subject_id' => 'sg_user_01']);
        $this->assertDatabaseCount('profile_snapshots', 1);

        // Step 3: GET /api/v1/auth/session — should be authenticated
        $this->getJson('/api/v1/auth/session')->assertOk();
    }

    public function test_invalid_state_rejected(): void
    {
        $this->getJson('/api/v1/auth/login');

        $this->postJson('/api/v1/auth/callback', ['code' => 'x', 'state' => 'tampered'])
            ->assertStatus(401);
    }

    public function test_invalid_token_rejected(): void
    {
        // GET /api/v1/auth/login to populate session
        $this->getJson('/api/v1/auth/login')->assertOk();
        $state = session('oidc.state');
        $nonce = session('oidc.nonce');

        // Sign id_token with a DIFFERENT (throwaway) key — signature verification fails
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
            'sg-mock-key-1', // same kid but different key
        );

        $issuer = rtrim((string) config('identity.oidc.issuer'), '/');

        Http::fake([
            $issuer.'/oauth/token' => Http::response([
                'id_token' => $idToken,
                'access_token' => 'at',
                'refresh_token' => 'rt',
                'expires_in' => 3600,
            ]),
            // JWKS returns MockKeys public key — won't match token signed with wrongKey
            $issuer.'/.well-known/jwks.json' => Http::response(MockKeys::jwks()),
        ]);

        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => $state])
            ->assertStatus(401);
    }
}
