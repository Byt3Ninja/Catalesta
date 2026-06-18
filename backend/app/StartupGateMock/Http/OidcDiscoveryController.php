<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class OidcDiscoveryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var string $issuer */
        $issuer = config('identity.oidc.issuer', 'http://startup-gate-mock:8080');

        /** @var list<string> $scopes */
        $scopes = config('identity.oidc.scopes', [
            'openid',
            'profile.basic.read',
            'profile.professional.read',
            'profile.founder.read',
            'profile.mentor.read',
            'profile.service_provider.read',
            'profile.startups.read',
            'profile.documents.read',
        ]);

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'userinfo_endpoint' => $issuer.'/oauth/userinfo',
            'jwks_uri' => $issuer.'/.well-known/jwks.json',
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => $scopes,
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
        ]);
    }
}
