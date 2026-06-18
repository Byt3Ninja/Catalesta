<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ErrorEnvelopeTest extends TestCase
{
    public function test_validation_errors_use_standard_envelope(): void
    {
        // /api/v1/organizations requires auth; unauthenticated => 401 envelope
        $res = $this->getJson('/api/v1/organizations');
        $res->assertStatus(401)
            ->assertJsonStructure(['error' => ['code', 'message', 'correlation_id']]);
    }
}
