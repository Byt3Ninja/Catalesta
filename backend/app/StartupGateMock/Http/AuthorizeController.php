<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use App\StartupGateMock\Support\SeedPersonas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Handles GET /oauth/authorize for the mock OIDC provider.
 *
 * Validates required PKCE + OIDC parameters, resolves the persona from
 * login_hint (defaulting to sg_user_01), stores a one-time authorization
 * code in cache, and redirects to redirect_uri with code + state.
 */
final class AuthorizeController extends Controller
{
    private const CODE_TTL_SECONDS = 300;

    public function __invoke(Request $request): RedirectResponse
    {
        // Validate required parameters
        $errors = $this->validateRequest($request);
        if ($errors !== []) {
            $redirectUri = $request->query('redirect_uri', '');
            $state = $request->query('state', '');

            return redirect()->away(
                $redirectUri.'?'.http_build_query([
                    'error' => 'invalid_request',
                    'error_description' => implode('; ', $errors),
                    'state' => $state,
                ])
            );
        }

        $sub = $request->query('login_hint', 'sg_user_01') ?: 'sg_user_01';

        // Fall back to sg_user_01 if sub not found
        if (SeedPersonas::find((string) $sub) === null) {
            $sub = 'sg_user_01';
        }

        $code = Str::random(40);
        $scopes = explode(' ', (string) $request->query('scope', 'openid'));

        Cache::put(
            'oidc_code:'.$code,
            [
                'sub' => (string) $sub,
                'code_challenge' => (string) $request->query('code_challenge'),
                'nonce' => (string) $request->query('nonce', ''),
                'redirect_uri' => (string) $request->query('redirect_uri'),
                'scopes' => $scopes,
            ],
            self::CODE_TTL_SECONDS
        );

        $redirectUri = (string) $request->query('redirect_uri');
        $state = (string) $request->query('state', '');

        return redirect()->away(
            $redirectUri.'?'.http_build_query([
                'code' => $code,
                'state' => $state,
            ])
        );
    }

    /**
     * @return list<string>
     */
    private function validateRequest(Request $request): array
    {
        $errors = [];

        if ($request->query('response_type') !== 'code') {
            $errors[] = 'response_type must be code';
        }

        if (! $request->query('client_id')) {
            $errors[] = 'client_id is required';
        }

        if (! $request->query('redirect_uri')) {
            $errors[] = 'redirect_uri is required';
        }

        if (! $request->query('code_challenge')) {
            $errors[] = 'code_challenge is required';
        }

        if ($request->query('code_challenge_method') !== 'S256') {
            $errors[] = 'code_challenge_method must be S256';
        }

        if (! $request->query('state')) {
            $errors[] = 'state is required';
        }

        if (! $request->query('nonce')) {
            $errors[] = 'nonce is required';
        }

        return $errors;
    }
}
