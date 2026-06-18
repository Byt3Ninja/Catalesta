<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Mock endpoint for program achievements.
 *
 * POST /api/v1/program-achievements
 *
 * Records that a participant earned an achievement in a program.
 * Returns 201 Created + {achievement_id}.
 * Resolves the caller from the Bearer token (same cache scheme as ProfileController).
 */
final class ProgramAchievementController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return $this->unauthorized();
        }

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get('oidc_access:'.$token);

        if ($cached === null) {
            return $this->unauthorized();
        }

        $achievementId = (string) Str::uuid();

        return response()->json(
            ['achievement_id' => $achievementId],
            201
        );
    }

    private function extractBearerToken(Request $request): ?string
    {
        $token = $request->bearerToken();

        return ($token !== null && $token !== '') ? $token : null;
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['error' => 'unauthorized', 'error_description' => 'Valid Bearer token required.'], 401);
    }
}
