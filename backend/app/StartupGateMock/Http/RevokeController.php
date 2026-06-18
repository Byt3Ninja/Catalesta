<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Handles POST /oauth/revoke for the mock OIDC provider.
 *
 * Accepts a token hint (access_token or refresh_token) and removes it
 * from cache. Always returns 200 per RFC 7009.
 */
final class RevokeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');

        if ($token !== '') {
            // Try both prefixes — RFC 7009 says revocation should be idempotent
            Cache::forget('oidc_access:'.$token);
            Cache::forget('oidc_refresh:'.$token);
        }

        return response()->json([], 200);
    }
}
