<?php

declare(strict_types=1);

namespace Tests\Contract;

use Tests\TestCase;

/**
 * Contract tests for the mock Startup Gate Profile API.
 *
 * Covers all 7 mock profile endpoints plus consent-gating and 401 behaviour.
 */
final class ProfileApiContractTest extends TestCase
{
    // -------------------------------------------------------------------------
    // PKCE helper
    // -------------------------------------------------------------------------

    /**
     * Obtains a mock access token for the given persona sub using PKCE flow.
     */
    private function obtainAccessToken(string $sub, string $scope = 'openid profile.basic.read profile.founder.read profile.startups.read'): string
    {
        $verifier = str_repeat('v', 64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $authorize = $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => 'program-platform',
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'scope' => $scope,
            'state' => 'st1',
            'nonce' => 'n1',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'login_hint' => $sub,
        ]));
        $authorize->assertRedirect();

        parse_str((string) parse_url((string) $authorize->headers->get('Location'), PHP_URL_QUERY), $q);

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $q['code'],
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'client_id' => 'program-platform',
            'code_verifier' => $verifier,
        ])->assertOk()->json();

        return (string) $token['access_token'];
    }

    // -------------------------------------------------------------------------
    // 401 guard
    // -------------------------------------------------------------------------

    public function test_me_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_unknown_token_returns_401(): void
    {
        $this->withToken('totally-unknown-token')
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // /api/v1/me  (sg_user_01 — founder only)
    // -------------------------------------------------------------------------

    public function test_me_returns_basic_identity(): void
    {
        $token = $this->obtainAccessToken('sg_user_01');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk();

        $response->assertJsonStructure(['sub', 'email', 'email_verified', 'name', 'locale']);
        $this->assertSame('sg_user_01', $response->json('sub'));
        $this->assertSame('founder.only@example.com', $response->json('email'));
        $this->assertTrue($response->json('email_verified'));
        $this->assertSame('Alex Founder', $response->json('name'));
        $this->assertSame('en', $response->json('locale'));
    }

    // -------------------------------------------------------------------------
    // /api/v1/me/profile
    // -------------------------------------------------------------------------

    public function test_me_profile_returns_profile_for_consented_user(): void
    {
        $token = $this->obtainAccessToken('sg_user_01');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me/profile')
            ->assertOk();

        // Should include profile fields since sg_user_01 has profile.basic.read and profile.founder.read
        $response->assertJsonStructure(['bio', 'avatar_url', 'location']);
        $this->assertSame('Serial entrepreneur building in fintech.', $response->json('bio'));
    }

    public function test_me_profile_omits_gated_sections_for_revoked_consent_user(): void
    {
        // sg_user_08 has consent_revoked=true, granted_scopes=['openid']
        $token = $this->obtainAccessToken('sg_user_08', 'openid');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me/profile')
            ->assertOk();

        // Should NOT contain bio/avatar_url/location (gated profile sections)
        $this->assertArrayNotHasKey('bio', $response->json());
        $this->assertArrayNotHasKey('avatar_url', $response->json());
        $this->assertArrayNotHasKey('location', $response->json());
    }

    // -------------------------------------------------------------------------
    // /api/v1/me/role-profiles
    // -------------------------------------------------------------------------

    public function test_me_role_profiles_returns_roles_for_founder(): void
    {
        $token = $this->obtainAccessToken('sg_user_01');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me/role-profiles')
            ->assertOk();

        $roles = $response->json();
        $this->assertIsArray($roles);
        $this->assertNotEmpty($roles);
        $this->assertSame('founder', $roles[0]['role']);
        $this->assertTrue($roles[0]['verified']);
    }

    public function test_me_role_profiles_marks_expired_verification(): void
    {
        // sg_user_10 has role_verification_expired=true, verified=false
        $token = $this->obtainAccessToken('sg_user_10', 'openid profile.basic.read profile.mentor.read');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me/role-profiles')
            ->assertOk();

        $roles = $response->json();
        $this->assertIsArray($roles);
        $this->assertNotEmpty($roles);
        $this->assertFalse($roles[0]['verified']);
        $this->assertArrayHasKey('expired_at', $roles[0]);
    }

    // -------------------------------------------------------------------------
    // /api/v1/me/startups
    // -------------------------------------------------------------------------

    public function test_me_startups_returns_startups_for_founder(): void
    {
        $token = $this->obtainAccessToken('sg_user_01');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me/startups')
            ->assertOk();

        $startups = $response->json();
        $this->assertIsArray($startups);
        $this->assertNotEmpty($startups);
        $this->assertSame('startup_001', $startups[0]['id']);
        $this->assertSame('FinEdge', $startups[0]['name']);
    }

    public function test_me_startups_returns_empty_for_mentor(): void
    {
        $token = $this->obtainAccessToken('sg_user_03', 'openid profile.basic.read profile.mentor.read');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me/startups')
            ->assertOk();

        $this->assertSame([], $response->json());
    }

    // -------------------------------------------------------------------------
    // /api/v1/me/consents
    // -------------------------------------------------------------------------

    public function test_me_consents_returns_granted_for_normal_user(): void
    {
        $token = $this->obtainAccessToken('sg_user_01');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me/consents')
            ->assertOk();

        $consents = $response->json();
        $this->assertIsArray($consents);
        $this->assertNotEmpty($consents);

        // Each item must have scope, granted, reference
        foreach ($consents as $consent) {
            $this->assertArrayHasKey('scope', $consent);
            $this->assertArrayHasKey('granted', $consent);
            $this->assertArrayHasKey('reference', $consent);
        }

        // sg_user_01 has two consents with granted_at
        $scopes = array_column($consents, 'scope');
        $this->assertContains('profile.basic.read', $scopes);
        $this->assertContains('profile.founder.read', $scopes);

        foreach ($consents as $consent) {
            $this->assertTrue($consent['granted']);
        }
    }

    public function test_me_consents_returns_granted_false_for_revoked_consent_user(): void
    {
        // sg_user_08 has consent_revoked=true, consents=[], granted_scopes=['openid']
        $token = $this->obtainAccessToken('sg_user_08', 'openid');

        $response = $this->withToken($token)
            ->getJson('/api/v1/me/consents')
            ->assertOk();

        $consents = $response->json();
        $this->assertIsArray($consents);

        // All consents should be granted:false for revoked persona
        foreach ($consents as $consent) {
            $this->assertArrayHasKey('granted', $consent);
            $this->assertFalse($consent['granted']);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/profile-update-proposals
    // -------------------------------------------------------------------------

    public function test_profile_update_proposal_returns_202_with_proposal_id(): void
    {
        $token = $this->obtainAccessToken('sg_user_01');

        $response = $this->withToken($token)
            ->postJson('/api/v1/profile-update-proposals', [
                'bio' => 'Updated bio text.',
                'location' => 'Nairobi, Kenya',
            ])
            ->assertStatus(202);

        $response->assertJsonStructure(['proposal_id']);
        $this->assertNotEmpty($response->json('proposal_id'));

        // Echoes accepted fields
        $response->assertJsonFragment(['bio' => 'Updated bio text.']);
        $response->assertJsonFragment(['location' => 'Nairobi, Kenya']);
    }

    public function test_profile_update_proposal_requires_auth(): void
    {
        $this->postJson('/api/v1/profile-update-proposals', ['bio' => 'test'])
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/program-achievements
    // -------------------------------------------------------------------------

    public function test_program_achievement_returns_201_with_achievement_id(): void
    {
        $token = $this->obtainAccessToken('sg_user_01');

        $response = $this->withToken($token)
            ->postJson('/api/v1/program-achievements', [
                'program_id' => 'prog_001',
                'achievement_type' => 'graduation',
            ])
            ->assertStatus(201);

        $response->assertJsonStructure(['achievement_id']);
        $this->assertNotEmpty($response->json('achievement_id'));
    }

    public function test_program_achievement_requires_auth(): void
    {
        $this->postJson('/api/v1/program-achievements', ['program_id' => 'prog_001'])
            ->assertUnauthorized();
    }
}
