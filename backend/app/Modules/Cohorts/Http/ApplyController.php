<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Telemetry\LearningTelemetry;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public application URL (FR-021). No auth/tenant — a cohort is public once
 * opened, so it is resolved without the tenant scope. Reports whether the cohort
 * is currently accepting applications; serving/submitting the form is Story 2.7.
 * Also the public emit point for the funnel's pre-auth telemetry (FR-080).
 */
final class ApplyController
{
    public function show(string $cohort, LearningTelemetry $telemetry): JsonResponse
    {
        // A cohort is public once opened — read across tenants via the sanctioned
        // system-context API (not withoutGlobalScope, per the tenancy arch test).
        // The published form definition is resolved in the same system context so
        // the public submit page (Story 2.7) can render the stepped form fields.
        /** @var array{0: Cohort|null, 1: FormVersion|null} $resolved */
        $resolved = app(TenantContext::class)->runAsSystem(function () use ($cohort): array {
            $found = Cohort::find($cohort);
            $form = $found?->form_version_id !== null ? FormVersion::find($found->form_version_id) : null;

            return [$found, $form];
        });
        [$cohortModel, $formVersion] = $resolved;

        if ($cohortModel === null) {
            throw new NotFoundHttpException('Cohort not found.');
        }

        // FR-080: record the public page view (best-effort, no PII). Stamped with
        // the cohort's org since the public request has no tenant context.
        $telemetry->record('application.viewed', $cohortModel->id, $cohortModel->organization_id);

        return response()->json([
            'open' => $cohortModel->isAcceptingSubmissions(),
            'cohort_id' => $cohortModel->id,
            'form_version_id' => $cohortModel->form_version_id,
            // The published, immutable field definition (jsonb) the applicant fills
            // in — null if the cohort has no published form yet.
            'form' => $formVersion?->definition,
        ]);
    }

    /**
     * POST /v1/apply/{cohort}/events — public best-effort telemetry beacon (FR-080).
     * The client fires `started` once, when the applicant enters their first answer.
     * No auth/tenant; the cohort's org is resolved under system context. Returns 204
     * regardless (beacon semantics — never block or leak), but rejects an unknown
     * event name with 422 so the client contract stays honest.
     */
    public function event(Request $request, string $cohort, LearningTelemetry $telemetry): Response
    {
        $validated = $request->validate([
            'event' => 'required|string|in:started',
        ]);

        $organizationId = app(TenantContext::class)->runAsSystem(
            fn (): ?string => Cohort::find($cohort)?->organization_id,
        );

        if ($organizationId !== null) {
            $telemetry->record('application.'.$validated['event'], $cohort, $organizationId);
        }

        return response()->noContent();
    }
}
