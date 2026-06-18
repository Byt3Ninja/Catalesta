<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use App\Modules\Identity\Domain\Exceptions\InvalidTokenException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestRsa;
use Tests\TestCase;

final class IdentityProviderValidationTest extends TestCase
{
    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keys = TestRsa::generate(); // helper in tests/Support; returns ['private','public','jwks']
        config(['identity.oidc.issuer' => 'https://issuer.test', 'identity.oidc.client_id' => 'program-platform']);
        Http::fake(['https://issuer.test/.well-known/jwks.json' => Http::response($this->keys['jwks'])]);
    }

    private function token(array $overrides = []): string
    {
        $claims = array_merge([
            'iss' => 'https://issuer.test', 'aud' => 'program-platform',
            'sub' => 'sg_user_01', 'nonce' => 'N1', 'exp' => time() + 300, 'iat' => time(),
        ], $overrides);

        return JWT::encode($claims, $this->keys['private'], 'RS256', 'sg-mock-key-1');
    }

    public function test_valid_token_returns_claims(): void
    {
        $claims = app(IdentityProvider::class)->validateIdToken($this->token(), 'N1');
        $this->assertSame('sg_user_01', $claims['sub']);
    }

    public function test_expired_token_rejected(): void
    {
        $this->expectException(InvalidTokenException::class);
        app(IdentityProvider::class)->validateIdToken($this->token(['exp' => time() - 10]), 'N1');
    }

    public function test_wrong_issuer_rejected(): void
    {
        $this->expectException(InvalidTokenException::class);
        app(IdentityProvider::class)->validateIdToken($this->token(['iss' => 'https://evil.test']), 'N1');
    }

    public function test_wrong_audience_rejected(): void
    {
        $this->expectException(InvalidTokenException::class);
        app(IdentityProvider::class)->validateIdToken($this->token(['aud' => 'someone-else']), 'N1');
    }

    public function test_nonce_mismatch_rejected(): void
    {
        $this->expectException(InvalidTokenException::class);
        app(IdentityProvider::class)->validateIdToken($this->token(['nonce' => 'OTHER']), 'N1');
    }
}
