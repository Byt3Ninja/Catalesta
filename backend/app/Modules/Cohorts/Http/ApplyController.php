<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public application URL (FR-021). No auth/tenant — a cohort is public once
 * opened, so it is resolved without the tenant scope. Reports whether the cohort
 * is currently accepting applications; serving/submitting the form is Story 2.7.
 */
final class ApplyController
{
    public function show(string $cohort): JsonResponse
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

        return response()->json([
            'open' => $cohortModel->isAcceptingSubmissions(),
            'cohort_id' => $cohortModel->id,
            'form_version_id' => $cohortModel->form_version_id,
            // The published, immutable field definition (jsonb) the applicant fills
            // in — null if the cohort has no published form yet.
            'form' => $formVersion?->definition,
        ]);
    }
}
