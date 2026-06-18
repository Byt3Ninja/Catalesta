<?php

declare(strict_types=1);

namespace Tests\Contract;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Tests\TestCase;

final class OidcTokenContractTest extends TestCase
{
    public function test_authorization_code_with_pkce_returns_valid_id_token(): void
    {
        $verifier = str_repeat('a', 64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $authorize = $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => 'program-platform',
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'scope' => 'openid profile.basic.read', 'state' => 'S1', 'nonce' => 'N1',
            'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
            'login_hint' => 'sg_user_01',
        ]));
        $authorize->assertRedirect();
        parse_str(parse_url($authorize->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('S1', $q['state']);

        $token = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'code' => $q['code'],
            'redirect_uri' => 'http://localhost:3000/auth/callback',
            'client_id' => 'program-platform', 'code_verifier' => $verifier,
        ])->assertOk()->json();

        $this->assertSame('Bearer', $token['token_type']);
        $jwks = $this->getJson('/.well-known/jwks.json')->json();
        $claims = (array) JWT::decode($token['id_token'], JWK::parseKeySet($jwks));
        $this->assertSame('sg_user_01', $claims['sub']);
        $this->assertArrayHasKey('email_verified', $claims);
        $this->assertArrayHasKey('profile_updated_at', $claims);
    }
}
