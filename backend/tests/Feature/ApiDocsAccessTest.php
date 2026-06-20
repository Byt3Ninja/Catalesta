<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The Scramble API docs viewer (/docs/api) is local-only by default. We widen it
 * to `staging` via the `viewApiDocs` gate, while keeping it locked in production.
 */
final class ApiDocsAccessTest extends TestCase
{
    public function test_docs_viewer_is_forbidden_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/docs/api')->assertForbidden();
    }

    public function test_docs_viewer_is_reachable_in_staging(): void
    {
        $this->app['env'] = 'staging';

        $this->get('/docs/api')->assertOk();
    }
}
