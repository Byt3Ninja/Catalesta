<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Unauthenticated requests to auth:sanctum routes must return a 401 JSON envelope
 * regardless of the Accept header — Laravel's default Authenticate middleware tries
 * to redirect non-JSON callers to a `login` named route, which doesn't exist in an
 * API-only app and crashes as RouteNotFoundException → 500. Fix: bootstrap/app.php
 * sets redirectGuestsTo to a closure returning null, so AuthenticationException
 * bubbles to the renderer and emits clean JSON.
 */
final class UnauthApiResponseShapeTest extends TestCase
{
    public function test_unauth_request_without_accept_json_returns_401_json(): void
    {
        // No Accept header at all — the canonical curl / external-client shape.
        $response = $this->call('GET', '/api/v1/cohorts');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_unauth_request_with_html_accept_returns_401_json_not_redirect(): void
    {
        // Browser-direct-nav shape — `Accept: text/html` used to trigger a redirect
        // to a non-existent `login` route → RouteNotFoundException → 500.
        $response = $this->call('GET', '/api/v1/cohorts', server: ['HTTP_ACCEPT' => 'text/html']);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_unauth_request_with_accept_json_still_returns_401_json(): void
    {
        // Baseline (was already working) — guard against regressing the canonical path.
        $response = $this->getJson('/api/v1/cohorts');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
