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
            ->assertHeader('X-Correlation-Id')
            ->assertJsonStructure(['error' => ['code', 'message', 'correlation_id']]);
    }

    public function test_inbound_correlation_id_is_echoed_back_unchanged(): void
    {
        $inbound = 'corr_test_123';

        $res = $this->withHeader('X-Correlation-Id', $inbound)
            ->getJson('/api/v1/organizations');

        $res->assertStatus(401)
            ->assertHeader('X-Correlation-Id', $inbound)
            ->assertJsonPath('error.correlation_id', $inbound);
    }
}
