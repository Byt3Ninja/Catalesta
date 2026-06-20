<?php

declare(strict_types=1);

namespace App\Modules\Applications\Http;

use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Applications\Http\Resources\SubmissionDetailResource;
use App\Modules\Applications\Http\Resources\SubmissionResource;
use App\Modules\Cohorts\Domain\Models\Cohort;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Operator-facing read API for application submissions (Story 2.8, FR-034).
 * Behind auth:sanctum + tenant. The cohort is resolved tenant-scoped, so a
 * foreign/unknown cohort 404s before any submission is read; the submission
 * query is itself BelongsToTenant-scoped, so isolation is enforced twice.
 *
 * The list's pagination `meta.total` is the funnel's `submitted` count. The
 * `viewed`/`started` counts come from Learning Telemetry (FR-080), which is not
 * built yet — the funnel and the operator UI are tracked as follow-ups.
 */
final class SubmissionController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/cohorts/{cohort}/submissions
     */
    public function index(string $cohort): AnonymousResourceCollection
    {
        $resolved = Cohort::query()->findOrFail($cohort);
        $this->authorize('viewAny', ApplicationSubmission::class);

        $submissions = ApplicationSubmission::query()
            ->where('cohort_id', $resolved->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id') // stable tiebreaker for same-instant submissions
            ->paginate(20);

        return SubmissionResource::collection($submissions);
    }

    /**
     * GET /api/v1/cohorts/{cohort}/submissions/{submission}
     */
    public function show(string $cohort, string $submission): SubmissionDetailResource
    {
        $resolved = Cohort::query()->findOrFail($cohort);

        $model = ApplicationSubmission::query()
            ->where('cohort_id', $resolved->id)
            ->findOrFail($submission);

        $this->authorize('view', $model);

        return new SubmissionDetailResource($model);
    }
}
