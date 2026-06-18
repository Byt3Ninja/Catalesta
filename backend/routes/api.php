<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Modules\Identity\Http\AuthController;
use App\Modules\Identity\Http\MeController;
use Illuminate\Support\Facades\Route;

/*
 | All public APIs are versioned (CLAUDE.md rule 12). The base API prefix is
 | "api" (configured in bootstrap/app.php); this group adds the "v1" segment,
 | yielding "/api/v1/...".
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('health');

    // Temporary stub – exercises 401 for ErrorEnvelopeTest; will be replaced by real resource.
    Route::middleware('auth:sanctum')->get('/organizations', fn () => response()->json([]));

    // Authentication (OIDC authorization-code + PKCE)
    Route::get('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/session', [AuthController::class, 'session'])->name('auth.session');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // /me — local projection + profile API passthroughs (consent enforced by StartupGate mock)
        Route::get('/me', [MeController::class, 'me'])->name('me');
        Route::get('/me/profile', [MeController::class, 'profile'])->name('me.profile');
        Route::get('/me/role-profiles', [MeController::class, 'roleProfiles'])->name('me.role-profiles');
        Route::get('/me/startups', [MeController::class, 'startups'])->name('me.startups');
    });
});
