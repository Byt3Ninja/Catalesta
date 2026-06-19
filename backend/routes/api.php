<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Modules\Cohorts\Http\CohortController;
use App\Modules\Identity\Http\AuthController;
use App\Modules\Identity\Http\MeController;
use App\Modules\Organizations\Http\MembershipController;
use App\Modules\Organizations\Http\OrganizationController;
use App\Modules\Programs\Http\ProgramController;
use App\Modules\Programs\Http\ProgramPolicyController;
use App\Modules\Programs\Http\ProgramRoleRequirementController;
use App\Modules\Programs\Http\ProgramTemplateController;
use App\Modules\Stages\Http\StageController;
use Illuminate\Support\Facades\Route;

/*
 | All public APIs are versioned (CLAUDE.md rule 12). The base API prefix is
 | "api" (configured in bootstrap/app.php); this group adds the "v1" segment,
 | yielding "/api/v1/...".
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('health');

    // Organization routes — NO tenant middleware for store + index
    // (no org context exists yet when creating; index lists across orgs)
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    });

    // Organization routes WITH tenant middleware (requires X-Organization-Id header + active membership)
    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::get('/organizations/{id}', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::patch('/organizations/{id}', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::post('/organizations/{org}/memberships', [MembershipController::class, 'store'])->name('organizations.memberships.store');
        Route::get('/organizations/{org}/memberships', [MembershipController::class, 'index'])->name('organizations.memberships.index');
    });

    // Program routes — all require auth:sanctum + tenant middleware (program always belongs to a tenant)
    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
        Route::get('/programs/{id}', [ProgramController::class, 'show'])->name('programs.show');
        Route::patch('/programs/{id}', [ProgramController::class, 'update'])->name('programs.update');
        Route::post('/programs/{id}/publish', [ProgramController::class, 'publish'])->name('programs.publish');
        Route::post('/programs/{id}/clone', [ProgramController::class, 'clone'])->name('programs.clone');

        // Program policies sub-resource
        Route::get('/programs/{program}/policies', [ProgramPolicyController::class, 'index'])->name('programs.policies.index');
        Route::post('/programs/{program}/policies', [ProgramPolicyController::class, 'store'])->name('programs.policies.store');

        // Program role requirements sub-resource
        Route::get('/programs/{program}/role-requirements', [ProgramRoleRequirementController::class, 'index'])->name('programs.role-requirements.index');
        Route::post('/programs/{program}/role-requirements', [ProgramRoleRequirementController::class, 'store'])->name('programs.role-requirements.store');

        // Cohort sub-resource (nested under program for create)
        Route::post('/programs/{program}/cohorts', [CohortController::class, 'store'])->name('programs.cohorts.store');

        // Cohort direct routes (show/update by id)
        Route::get('/cohorts/{id}', [CohortController::class, 'show'])->name('cohorts.show');
        Route::patch('/cohorts/{id}', [CohortController::class, 'update'])->name('cohorts.update');

        // Stage sub-resource routes (nested under program)
        Route::get('/programs/{program}/stages', [StageController::class, 'index'])->name('programs.stages.index');
        Route::post('/programs/{program}/stages', [StageController::class, 'store'])->name('programs.stages.store');
        Route::post('/programs/{program}/stages/reorder', [StageController::class, 'reorder'])->name('programs.stages.reorder');

        // Stage direct routes (by id)
        Route::patch('/stages/{id}', [StageController::class, 'update'])->name('stages.update');
        Route::post('/stages/{id}/publish', [StageController::class, 'publish'])->name('stages.publish');

        // Program template routes
        Route::post('/program-templates', [ProgramTemplateController::class, 'store'])->name('program-templates.store');
        Route::post('/program-templates/{templateId}/instantiate', [ProgramTemplateController::class, 'instantiate'])->name('program-templates.instantiate');
    });

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
