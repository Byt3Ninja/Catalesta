<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function test_builtin_liveness_probe_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_reports_all_dependencies_ok(): void
    {
        Storage::fake('s3');

        $connection = Mockery::mock();
        $connection->shouldReceive('ping')->andReturnTrue();
        Redis::shouldReceive('connection')->andReturn($connection);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'program-platform-api')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.redis.status', 'ok')
            ->assertJsonPath('checks.object_storage.status', 'ok');
    }

    public function test_health_endpoint_is_degraded_when_a_dependency_fails(): void
    {
        Storage::fake('s3');

        $connection = Mockery::mock();
        $connection->shouldReceive('ping')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('connection')->andReturn($connection);

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.redis.status', 'error');
    }
}
