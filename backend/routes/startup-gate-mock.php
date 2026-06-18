<?php

declare(strict_types=1);

use App\StartupGateMock\Http\AuthorizeController;
use App\StartupGateMock\Http\JwksController;
use App\StartupGateMock\Http\LogoutController;
use App\StartupGateMock\Http\OidcDiscoveryController;
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
