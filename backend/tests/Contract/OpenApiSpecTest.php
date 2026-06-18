<?php

declare(strict_types=1);

namespace Tests\Contract;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Contract guard for the generated OpenAPI document.
 *
 * Scramble derives the spec from the live routes/FormRequests/Resources. This test proves the
 * exporter still works and that the committed baseline (openapi/openapi.json) is in sync with
 * the current API surface. If it fails after adding/changing a route, regenerate the baseline:
 *
 *     php artisan scramble:export
 */
final class OpenApiSpecTest extends TestCase
{
    private const BASELINE = 'openapi/openapi.json';

    public function test_committed_baseline_exists_and_is_valid_openapi_3_1(): void
    {
        $spec = $this->readBaseline();

        $this->assertArrayHasKey('openapi', $spec, 'Baseline is missing the openapi version field.');
        $this->assertMatchesRegularExpression('/^3\.1\.\d+$/', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertNotEmpty($spec['paths'], 'Baseline documents no paths.');
    }

    public function test_baseline_api_surface_matches_freshly_generated_spec(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'openapi_').'.json';

        try {
            $exit = Artisan::call('scramble:export', ['--path' => $tmp, '--silent' => true]);
            $this->assertSame(0, $exit, 'scramble:export failed.');

            $generated = json_decode((string) file_get_contents($tmp), true, flags: JSON_THROW_ON_ERROR);
            $baseline = $this->readBaseline();

            $this->assertSame(
                $this->surface($baseline),
                $this->surface($generated),
                'OpenAPI baseline is stale. Run `php artisan scramble:export` and commit openapi/openapi.json.',
            );
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * The env-independent API surface: documented paths mapped to their sorted HTTP methods.
     * Excludes server URLs and version strings, which vary by environment and would flake.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, list<string>>
     */
    private function surface(array $spec): array
    {
        $surface = [];

        foreach (($spec['paths'] ?? []) as $path => $operations) {
            $methods = array_values(array_filter(
                array_keys($operations),
                static fn (string $key): bool => in_array(
                    strtolower($key),
                    ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'],
                    true,
                ),
            ));
            sort($methods);
            $surface[$path] = $methods;
        }

        ksort($surface);

        return $surface;
    }

    /**
     * @return array<string, mixed>
     */
    private function readBaseline(): array
    {
        $path = base_path(self::BASELINE);
        $this->assertFileExists($path, 'Missing OpenAPI baseline; run `php artisan scramble:export`.');

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
