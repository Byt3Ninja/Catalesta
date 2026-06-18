<?php

declare(strict_types=1);

use App\StartupGateMock\Http\JwksController;
use App\StartupGateMock\Http\OidcDiscoveryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Startup Gate Mock OIDC Routes
|--------------------------------------------------------------------------
|
| These routes are only registered when app.role === 'mock' or in the
| testing environment. They expose the OIDC discovery document and JWKS
| endpoint that the platform adapter (Task 3.2) validates tokens against.
|
| In production the platform role will NOT load this file.
|
*/

Route::get('/.well-known/openid-configuration', OidcDiscoveryController::class)
    ->name('oidc.discovery');

Route::get('/.well-known/jwks.json', JwksController::class)
    ->name('oidc.jwks');
