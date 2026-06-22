<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Modules\Applications\Http\SubmissionController;
use App\Modules\Applications\Http\SubmitController;
use App\Modules\Cohorts\Http\ApplyController;
use App\Modules\Cohorts\Http\CohortController;
use App\Modules\Identity\Http\AuthController;
use App\Modules\Identity\Http\MeController;
use App\Modules\Identity\Http\NativeAuthController;
use App\Modules\Organizations\Http\MembershipController;
use App\Modules\Organizations\Http\OrganizationController;
use App\Modules\Programs\Http\ProgramController;
use App\Modules\Programs\Http\ProgramPolicyController;
use App\Modules\Programs\Http\ProgramRoleRequirementController;
use App\Modules\Programs\Http\ProgramTemplateController;
use App\Modules\Programs\Http\TrackController;
use App\Modules\Reporting\Http\FunnelController;
use App\Modules\Stages\Http\StageController;
use App\Modules\Stages\Http\StageDependencyController;
use Illuminate\Support\Facades\Route;

/*
 | All public APIs are versioned (CLAUDE.md rule 12). The base API prefix is
 | "api" (configured in bootstrap/app.php); this group adds the "v1" segment,
 | yielding "/api/v1/...".
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('health');

    // Public application URL (FR-021) — no auth/tenant; a cohort is public once opened.
    Route::get('/apply/{cohort}', [ApplyController::class, 'show'])->name('apply.show');

    // Public submit (Story 2.7) — authenticated applicant (`sub`), NO tenant
    // middleware (the applicant has no org; the submission inherits the cohort's).
    Route::post('/apply/{cohort}/submit', [SubmitController::class, 'store'])
        ->middleware('auth:sanctum')->name('apply.submit');

    // Public telemetry beacon (Story 2.8, FR-080) — no auth/tenant, best-effort.
    // The client fires `started` once when the applicant enters their first answer.
    Route::post('/apply/{cohort}/events', [ApplyController::class, 'event'])->name('apply.events');

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

        // Cohort list (operator Home, Story 1.5) — tenant-scoped index
        Route::get('/cohorts', [CohortController::class, 'index'])->name('cohorts.index');

        // Cohort direct routes (show/update by id)
        Route::get('/cohorts/{id}', [CohortController::class, 'show'])->name('cohorts.show');
        Route::patch('/cohorts/{id}', [CohortController::class, 'update'])->name('cohorts.update');

        // Operator submission read API (Story 2.8, FR-034) — tenant-scoped list + detail.
        Route::get('/cohorts/{cohort}/submissions', [SubmissionController::class, 'index'])->name('cohorts.submissions.index');
        Route::get('/cohorts/{cohort}/submissions/{submission}', [SubmissionController::class, 'show'])->name('cohorts.submissions.show');

        // Operator funnel (Story 2.8, FR-080) — viewed/started (telemetry) + submitted (durable).
        Route::get('/cohorts/{cohort}/funnel', [FunnelController::class, 'show'])->name('cohorts.funnel');

        // Stage sub-resource routes (nested under program)
        Route::get('/programs/{program}/stages', [StageController::class, 'index'])->name('programs.stages.index');
        Route::post('/programs/{program}/stages', [StageController::class, 'store'])->name('programs.stages.store');
        Route::post('/programs/{program}/stages/reorder', [StageController::class, 'reorder'])->name('programs.stages.reorder');

        // Stage direct routes (by id)
        Route::patch('/stages/{id}', [StageController::class, 'update'])->name('stages.update');
        Route::post('/stages/{id}/publish', [StageController::class, 'publish'])->name('stages.publish');

        // Stage dependency sub-resource
        Route::get('/programs/{program}/stages/{stage}/dependencies', [StageDependencyController::class, 'index'])->name('programs.stages.dependencies.index');
        Route::post('/programs/{program}/stages/{stage}/dependencies', [StageDependencyController::class, 'store'])->name('programs.stages.dependencies.store');
        Route::delete('/stage-dependencies/{id}', [StageDependencyController::class, 'destroy'])->name('stage-dependencies.destroy');

        // Track sub-resource routes (nested under program for create/list)
        Route::get('/programs/{program}/tracks', [TrackController::class, 'index'])->name('programs.tracks.index');
        Route::post('/programs/{program}/tracks', [TrackController::class, 'store'])->name('programs.tracks.store');

        // Track direct routes (by id for update/delete)
        Route::patch('/tracks/{id}', [TrackController::class, 'update'])->name('tracks.update');
        Route::delete('/tracks/{id}', [TrackController::class, 'destroy'])->name('tracks.destroy');

        // Program template routes
        Route::post('/program-templates', [ProgramTemplateController::class, 'store'])->name('program-templates.store');
        Route::post('/program-templates/{templateId}/instantiate', [ProgramTemplateController::class, 'instantiate'])->name('program-templates.instantiate');
    });

    // Authentication (OIDC authorization-code + PKCE)
    Route::get('/auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');

    // Native auth — registration (public)
    Route::post('/auth/register', [NativeAuthController::class, 'register'])
        ->middleware('throttle:auth-register')->name('auth.register');

    // Native auth — email verification (signed link, public)
    Route::get('/auth/email/verify/{id}/{hash}', [NativeAuthController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('auth.email.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/session', [AuthController::class, 'session'])->name('auth.session');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Native auth — resend verification email
        Route::post('/auth/email/resend', [NativeAuthController::class, 'resend'])
            ->middleware('throttle:auth-resend')->name('auth.email.resend');

        // /me — local projection + profile API passthroughs (consent enforced by StartupGate mock)
        Route::get('/me', [MeController::class, 'me'])->name('me');
        Route::get('/me/profile', [MeController::class, 'profile'])->name('me.profile');
        Route::get('/me/role-profiles', [MeController::class, 'roleProfiles'])->name('me.role-profiles');
        Route::get('/me/startups', [MeController::class, 'startups'])->name('me.startups');
    });
});
