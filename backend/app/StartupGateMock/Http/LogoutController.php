<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Handles POST /oauth/logout for the mock OIDC provider.
 *
 * In the mock this is a no-op that acknowledges the logout request.
 * A production OIDC provider would invalidate sessions and notify
 * back-channel logout subscribers.
 */
final class LogoutController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([], 200);
    }
}
