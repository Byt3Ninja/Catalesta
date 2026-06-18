<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CsrfCookieTest extends TestCase
{
    public function test_csrf_cookie_endpoint_returns_204_and_sets_xsrf_token_cookie(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertStatus(204);
        $this->assertTrue(
            str_contains($response->headers->get('Set-Cookie', ''), 'XSRF-TOKEN'),
            'Expected XSRF-TOKEN cookie in Set-Cookie header',
        );
    }
}
