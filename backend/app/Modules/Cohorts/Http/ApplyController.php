<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http;

use App\Modules\Cohorts\Domain\Models\Cohort;
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
        $resolved = app(TenantContext::class)->runAsSystem(fn () => Cohort::find($cohort));
        if ($resolved === null) {
            throw new NotFoundHttpException('Cohort not found.');
        }

        return response()->json([
            'open' => $resolved->isAcceptingSubmissions(),
            'cohort_id' => $resolved->id,
            'form_version_id' => $resolved->form_version_id,
        ]);
    }
}
