<?php

declare(strict_types=1);

namespace App\StartupGateMock;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Startup Gate mock OIDC routes and related bindings.
 *
 * Routes are loaded ONLY when:
 *   - config('app.role') === 'mock'  (the startup-gate-mock Docker container), OR
 *   - app()->environment('testing')  (contract/feature tests run in-process)
 *
 * The platform role in production never loads this provider's routes,
 * preventing mock endpoints from appearing in the real application.
 */
final class StartupGateMockServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->shouldRegisterRoutes()) {
            return;
        }

        Route::middleware('web')
            ->group(base_path('routes/startup-gate-mock.php'));
    }

    private function shouldRegisterRoutes(): bool
    {
        return config('app.role') === 'mock'
            || $this->app->environment('testing');
    }
}
