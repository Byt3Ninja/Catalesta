<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use App\StartupGateMock\Support\SeedPersonas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Handles GET /oauth/userinfo for the mock OIDC provider.
 *
 * Resolves the Bearer access token from cache and returns
 * standard OIDC userinfo claims for the associated persona.
 */
final class UserInfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $authorization = $request->header('Authorization', '');
        $accessToken = '';

        if (str_starts_with((string) $authorization, 'Bearer ')) {
            $accessToken = substr((string) $authorization, 7);
        }

        if ($accessToken === '') {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Bearer token required',
            ], 401);
        }

        /** @var array<string, mixed>|null $stored */
        $stored = Cache::get('oidc_access:'.$accessToken);

        if ($stored === null) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Access token not found or expired',
            ], 401);
        }

        $sub = (string) $stored['sub'];
        $persona = SeedPersonas::find($sub);

        if ($persona === null) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Unknown subject',
            ], 401);
        }

        return response()->json([
            'sub' => $sub,
            'email' => $persona['email'],
            'email_verified' => $persona['email_verified'],
            'name' => $persona['name'],
            'locale' => $persona['locale'],
            'profile_updated_at' => $persona['profile_updated_at'],
        ]);
    }
}
