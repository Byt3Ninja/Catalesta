<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\StartupGate;

use App\Modules\Identity\Domain\Contracts\IdentityProvider;
use App\Modules\Identity\Domain\Exceptions\InvalidTokenException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

final class StartupGateIdentityProvider implements IdentityProvider
{
    /**
     * {@inheritdoc}
     */
    public function validateIdToken(string $idToken, string $expectedNonce): array
    {
        try {
            $issuer = (string) config('identity.oidc.issuer');
            $clientId = (string) config('identity.oidc.client_id');

            $jwksResponse = Http::get($issuer.'/.well-known/jwks.json');
            $jwks = $jwksResponse->json();

            if (! is_array($jwks)) {
                throw new InvalidTokenException('Failed to fetch JWKS');
            }

            $keySet = JWK::parseKeySet($jwks);

            $decoded = JWT::decode($idToken, $keySet);

            /** @var array<string,mixed> $claims */
            $claims = (array) $decoded;

            if (($claims['iss'] ?? null) !== $issuer) {
                throw new InvalidTokenException('Token issuer mismatch');
            }

            $aud = $claims['aud'] ?? null;
            if (is_array($aud)) {
                if (! in_array($clientId, $aud, true)) {
                    throw new InvalidTokenException('Token audience mismatch');
                }
            } elseif ($aud !== $clientId) {
                throw new InvalidTokenException('Token audience mismatch');
            }

            if (($claims['nonce'] ?? null) !== $expectedNonce) {
                throw new InvalidTokenException('Token nonce mismatch');
            }

            return $claims;
        } catch (InvalidTokenException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidTokenException('Token validation failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildAuthorizationUrl(string $state, string $nonce, string $codeChallenge, array $scopes): string
    {
        $issuer = (string) config('identity.oidc.issuer');

        $params = [
            'response_type' => 'code',
            'client_id' => config('identity.oidc.client_id'),
            'redirect_uri' => config('identity.oidc.redirect_uri'),
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        return $issuer.'/oauth/authorize?'.http_build_query($params);
    }

    /**
     * {@inheritdoc}
     */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $issuer = (string) config('identity.oidc.issuer');

        $response = Http::asForm()->post($issuer.'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('identity.oidc.client_id'),
            'client_secret' => config('identity.oidc.client_secret'),
            'redirect_uri' => config('identity.oidc.redirect_uri'),
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);

        /** @var array{id_token:string, access_token:string, refresh_token:?string, expires_in:int} */
        return $response->json();
    }

    /**
     * {@inheritdoc}
     */
    public function refresh(string $refreshToken): array
    {
        $issuer = (string) config('identity.oidc.issuer');

        $response = Http::asForm()->post($issuer.'/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('identity.oidc.client_id'),
            'client_secret' => config('identity.oidc.client_secret'),
            'refresh_token' => $refreshToken,
        ]);

        /** @var array{access_token:string, refresh_token:?string, expires_in:int} */
        return $response->json();
    }

    /**
     * {@inheritdoc}
     */
    public function revoke(string $token): void
    {
        $issuer = (string) config('identity.oidc.issuer');

        Http::asForm()->post($issuer.'/oauth/revoke', [
            'client_id' => config('identity.oidc.client_id'),
            'client_secret' => config('identity.oidc.client_secret'),
            'token' => $token,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function userinfo(string $accessToken): array
    {
        $issuer = (string) config('identity.oidc.issuer');

        $response = Http::withToken($accessToken)->get($issuer.'/oauth/userinfo');

        return $response->json();
    }
}
