<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Guards the two real-Startup-Gate conformance points the mock did not exercise:
 *  1. UserInfo lives at the discovery `userinfo_endpoint` (PROFILE_API_BASE_URL + /me,
 *     e.g. https://startup-gate.net/sg/api/v1/me), NOT issuer + /oauth/userinfo.
 *  2. The token endpoint advertises `client_secret_basic` only, so client
 *     credentials travel in the HTTP Basic Authorization header — never in the body.
 */
final class StartupGateProviderEndpointsTest extends TestCase
{
    private const ISSUER = 'https://issuer.test';

    private const CLIENT_ID = 'sg_client_123';

    private const CLIENT_SECRET = 's3cr3t-value';

    private const PROFILE_BASE = 'https://issuer.test/sg/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'identity.oidc.issuer' => self::ISSUER,
            'identity.oidc.client_id' => self::CLIENT_ID,
            'identity.oidc.client_secret' => self::CLIENT_SECRET,
            'identity.profile_api_base_url' => self::PROFILE_BASE,
        ]);
    }

    private function expectedBasicHeader(): string
    {
        return 'Basic '.base64_encode(self::CLIENT_ID.':'.self::CLIENT_SECRET);
    }

    public function test_userinfo_calls_discovery_userinfo_endpoint_with_bearer_token(): void
    {
        Http::fake([
            self::PROFILE_BASE.'/me' => Http::response(['sub' => 'sg_user_01', 'email' => 'f@example.com']),
        ]);

        $claims = app(IdentityProvider::class)->userinfo('access-token-xyz');

        $this->assertSame('sg_user_01', $claims['sub']);

        Http::assertSent(fn (Request $r): bool => $r->url() === self::PROFILE_BASE.'/me'
            && $r->hasHeader('Authorization', 'Bearer access-token-xyz'));
    }

    public function test_userinfo_does_not_call_legacy_oauth_userinfo_path(): void
    {
        Http::fake([
            self::PROFILE_BASE.'/me' => Http::response(['sub' => 'sg_user_01']),
        ]);

        app(IdentityProvider::class)->userinfo('access-token-xyz');

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/oauth/userinfo'));
    }

    public function test_exchange_code_authenticates_with_http_basic(): void
    {
        Http::fake([
            self::ISSUER.'/oauth/token' => Http::response([
                'id_token' => 'id', 'access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600,
            ]),
        ]);

        app(IdentityProvider::class)->exchangeCode('auth-code', 'verifier');

        Http::assertSent(fn (Request $r): bool => $r->url() === self::ISSUER.'/oauth/token'
            && $r->hasHeader('Authorization', $this->expectedBasicHeader()));
    }

    public function test_exchange_code_does_not_put_client_secret_in_body(): void
    {
        Http::fake([
            self::ISSUER.'/oauth/token' => Http::response([
                'id_token' => 'id', 'access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600,
            ]),
        ]);

        app(IdentityProvider::class)->exchangeCode('auth-code', 'verifier');

        Http::assertSent(fn (Request $r): bool => ! array_key_exists('client_secret', (array) $r->data()));
    }

    public function test_refresh_authenticates_with_http_basic(): void
    {
        Http::fake([
            self::ISSUER.'/oauth/token' => Http::response([
                'access_token' => 'at2', 'refresh_token' => 'rt2', 'expires_in' => 3600,
            ]),
        ]);

        app(IdentityProvider::class)->refresh('refresh-token');

        Http::assertSent(fn (Request $r): bool => $r->url() === self::ISSUER.'/oauth/token'
            && $r->hasHeader('Authorization', $this->expectedBasicHeader())
            && ! array_key_exists('client_secret', (array) $r->data()));
    }

    public function test_revoke_authenticates_with_http_basic(): void
    {
        Http::fake([
            self::ISSUER.'/oauth/revoke' => Http::response([], 200),
        ]);

        app(IdentityProvider::class)->revoke('some-token');

        Http::assertSent(fn (Request $r): bool => $r->url() === self::ISSUER.'/oauth/revoke'
            && $r->hasHeader('Authorization', $this->expectedBasicHeader())
            && ! array_key_exists('client_secret', (array) $r->data()));
    }
}
