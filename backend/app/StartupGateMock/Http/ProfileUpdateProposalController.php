<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Mock endpoint for profile update proposals.
 *
 * POST /api/v1/profile-update-proposals
 *
 * Accepts any fields, echoes them back with a generated proposal_id.
 * Returns 202 Accepted — the proposal is queued, not immediately applied.
 * Resolves the caller from the Bearer token (same cache scheme as ProfileController).
 */
final class ProfileUpdateProposalController extends Controller
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

        $proposalId = (string) Str::uuid();

        /** @var array<string, mixed> $fields */
        $fields = $request->all();

        return response()->json(
            array_merge(['proposal_id' => $proposalId], $fields),
            202
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
