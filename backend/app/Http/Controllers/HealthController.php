<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Liveness/readiness probe for the platform.
 *
 * Reports connectivity to the core infrastructure dependencies declared in
 * docs/02-system-architecture.md and docs/13-devops.md: PostgreSQL, Redis,
 * and S3-compatible object storage. Returns HTTP 200 when all checks pass
 * and HTTP 503 when any dependency is unreachable.
 */
final class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $checks = [
            'database' => $this->check(static function (): void {
                DB::connection()->getPdo();
                DB::select('select 1');
            }),
            'redis' => $this->check(static function (): void {
                Redis::connection()->ping();
            }),
            'object_storage' => $this->check(static function (): void {
                // A bucket/dir listing is enough to prove credentials + reachability.
                Storage::disk('s3')->directories();
            }),
        ];

        $ok = ! in_array('error', array_column($checks, 'status'), true);

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'service' => 'program-platform-api',
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    /**
     * @param  callable():void  $probe
     * @return array{status: string, message?: string}
     */
    private function check(callable $probe): array
    {
        try {
            $probe();

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
