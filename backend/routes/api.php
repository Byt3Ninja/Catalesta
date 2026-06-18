<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
 | All public APIs are versioned (CLAUDE.md rule 12). The base API prefix is
 | "api" (configured in bootstrap/app.php); this group adds the "v1" segment,
 | yielding "/api/v1/...".
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('health');
});
