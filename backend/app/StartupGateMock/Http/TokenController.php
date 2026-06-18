<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use App\StartupGateMock\Support\MockKeys;
use App\StartupGateMock\Support\SeedPersonas;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Handles POST /oauth/token for the mock OIDC provider.
 *
 * Supports grant_type=authorization_code (with PKCE S256 verification)
 * and grant_type=refresh_token.
 *
 * On success mints:
 *   - id_token: RS256 JWT signed with MockKeys private key
 *   - access_token: opaque random string cached → {sub, scopes}
 *   - refresh_token: opaque random string cached → sub
 */
final class TokenController extends Controller
{
    private const ACCESS_TOKEN_TTL = 3600;

    private const REFRESH_TOKEN_TTL = 86400 * 30;

    public function __invoke(Request $request): JsonResponse
    {
        $grantType = $request->input('grant_type');

        return match ($grantType) {
            'authorization_code' => $this->handleAuthorizationCode($request),
            'refresh_token' => $this->handleRefreshToken($request),
            default => response()->json([
                'error' => 'unsupported_grant_type',
                'error_description' => 'Supported grant types: authorization_code, refresh_token',
            ], 400),
        };
    }

    private function handleAuthorizationCode(Request $request): JsonResponse
    {
        $code = (string) $request->input('code', '');
        $codeVerifier = (string) $request->input('code_verifier', '');
        $redirectUri = (string) $request->input('redirect_uri', '');

        if ($code === '' || $codeVerifier === '' || $redirectUri === '') {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'code, code_verifier, and redirect_uri are required',
            ], 400);
        }

        $cacheKey = 'oidc_code:'.$code;
        /** @var array<string, mixed>|null $stored */
        $stored = Cache::get($cacheKey);

        if ($stored === null) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Authorization code not found or expired',
            ], 400);
        }

        // Verify PKCE S256: base64url(sha256(verifier, raw)) must equal stored challenge
        $computedChallenge = rtrim(
            strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'),
            '='
        );

        if (! hash_equals((string) $stored['code_challenge'], $computedChallenge)) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'PKCE code_verifier does not match code_challenge',
            ], 400);
        }

        if ($stored['redirect_uri'] !== $redirectUri) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'redirect_uri mismatch',
            ], 400);
        }

        // One-time use: delete the code immediately
        Cache::forget($cacheKey);

        $sub = (string) $stored['sub'];
        /** @var list<string> $scopes */
        $scopes = (array) $stored['scopes'];
        $nonce = (string) $stored['nonce'];

        return $this->issueTokens($sub, $scopes, $nonce);
    }

    private function handleRefreshToken(Request $request): JsonResponse
    {
        $refreshToken = (string) $request->input('refresh_token', '');

        if ($refreshToken === '') {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'refresh_token is required',
            ], 400);
        }

        $cacheKey = 'oidc_refresh:'.$refreshToken;
        /** @var array<string, mixed>|null $stored */
        $stored = Cache::get($cacheKey);

        if ($stored === null) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Refresh token not found or expired',
            ], 400);
        }

        $sub = (string) $stored['sub'];
        /** @var list<string> $scopes */
        $scopes = (array) ($stored['scopes'] ?? ['openid']);

        return $this->issueTokens($sub, $scopes, '');
    }

    /**
     * @param  list<string>  $scopes
     */
    private function issueTokens(string $sub, array $scopes, string $nonce): JsonResponse
    {
        $persona = SeedPersonas::find($sub);

        if ($persona === null) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Unknown subject',
            ], 400);
        }

        $now = time();
        $issuer = (string) config('identity.oidc.issuer', 'http://startup-gate-mock:8080');
        $clientId = (string) config('identity.oidc.client_id', 'program-platform');

        // Build ID token claims
        $claims = [
            'iss' => $issuer,
            'aud' => $clientId,
            'sub' => $sub,
            'email' => $persona['email'],
            'email_verified' => $persona['email_verified'],
            'name' => $persona['name'],
            'locale' => $persona['locale'],
            'profile_updated_at' => $persona['profile_updated_at'],
            'iat' => $now,
            'exp' => $now + self::ACCESS_TOKEN_TTL,
        ];

        if ($nonce !== '') {
            $claims['nonce'] = $nonce;
        }

        // Sign id_token with MockKeys private key (RS256)
        $idToken = JWT::encode(
            $claims,
            MockKeys::privateKeyPem(),
            'RS256',
            MockKeys::kid()
        );

        // Mint opaque access token
        $accessToken = Str::random(64);
        Cache::put('oidc_access:'.$accessToken, [
            'sub' => $sub,
            'scopes' => $scopes,
        ], self::ACCESS_TOKEN_TTL);

        // Mint opaque refresh token
        $refreshToken = Str::random(64);
        Cache::put('oidc_refresh:'.$refreshToken, [
            'sub' => $sub,
            'scopes' => $scopes,
        ], self::REFRESH_TOKEN_TTL);

        return response()->json([
            'token_type' => 'Bearer',
            'id_token' => $idToken,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => self::ACCESS_TOKEN_TTL,
            'scope' => implode(' ', $scopes),
        ]);
    }
}
