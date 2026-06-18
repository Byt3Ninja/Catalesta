<?php

declare(strict_types=1);

use App\StartupGateMock\Http\AuthorizeController;
use App\StartupGateMock\Http\JwksController;
use App\StartupGateMock\Http\LogoutController;
use App\StartupGateMock\Http\OidcDiscoveryController;
use App\StartupGateMock\Http\ProfileController;
use App\StartupGateMock\Http\ProfileUpdateProposalController;
use App\StartupGateMock\Http\ProgramAchievementController;
use App\StartupGateMock\Http\RevokeController;
use App\StartupGateMock\Http\TokenController;
use App\StartupGateMock\Http\UserInfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Startup Gate Mock OIDC Routes
|--------------------------------------------------------------------------
|
| These routes are only registered when app.role === 'mock' or in the
| testing environment. They expose the OIDC discovery document, JWKS
| endpoint, and the full OAuth/OIDC core flows.
|
| In production the platform role will NOT load this file.
|
*/

// Discovery + JWKS (Task 4.1)
Route::get('/.well-known/openid-configuration', OidcDiscoveryController::class)
    ->name('oidc.discovery');

Route::get('/.well-known/jwks.json', JwksController::class)
    ->name('oidc.jwks');

// OAuth / OIDC core (Task 4.2)
Route::get('/oauth/authorize', AuthorizeController::class)
    ->name('oauth.authorize');

Route::post('/oauth/token', TokenController::class)
    ->name('oauth.token');

Route::get('/oauth/userinfo', UserInfoController::class)
    ->name('oauth.userinfo');

Route::post('/oauth/revoke', RevokeController::class)
    ->name('oauth.revoke');

Route::post('/oauth/logout', LogoutController::class)
    ->name('oauth.logout');

/*
|--------------------------------------------------------------------------
| Mock Profile API (Task 4.3)
|--------------------------------------------------------------------------
|
| These routes mirror the Startup Gate profile API contract at /api/v1/*.
| They are mounted under the 'api' middleware group so they share the same
| URL namespace as the platform routes but remain isolated in this file.
| No platform module registers routes at /api/v1/me, so there is no
| collision in testing or mock mode.
|
*/

Route::middleware('api')->prefix('api/v1')->group(function (): void {
    // Identity summary
    Route::get('/me', [ProfileController::class, 'me'])
        ->name('mock.profile.me');

    // Consent-aware profile
    Route::get('/me/profile', [ProfileController::class, 'profile'])
        ->name('mock.profile.profile');

    // Role profiles (respects expiry flags)
    Route::get('/me/role-profiles', [ProfileController::class, 'roleProfiles'])
        ->name('mock.profile.role-profiles');

    // Startups
    Route::get('/me/startups', [ProfileController::class, 'startups'])
        ->name('mock.profile.startups');

    // Consent list
    Route::get('/me/consents', [ProfileController::class, 'consents'])
        ->name('mock.profile.consents');

    // Profile update proposal
    Route::post('/profile-update-proposals', ProfileUpdateProposalController::class)
        ->name('mock.profile.update-proposal');

    // Program achievement
    Route::post('/program-achievements', ProgramAchievementController::class)
        ->name('mock.program.achievement');
});
