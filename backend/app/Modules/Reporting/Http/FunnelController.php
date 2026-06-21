<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http;

use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Reporting\Domain\Models\LearningEvent;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Operator funnel for a cohort (FR-080 / Story 2.8). Behind auth:sanctum + tenant.
 * The cohort is resolved tenant-scoped (foreign/unknown → 404, AR-6); the event and
 * submission queries are BelongsToTenant-scoped, so isolation is enforced twice.
 *
 * `submitted` is the durable application_submissions count — the authoritative
 * number, NEVER the lossy telemetry count. `viewed`/`started` are telemetry counts;
 * `viewed` is clamped >= `started` because best-effort beacons can be lost (the
 * "views are approximate" microcopy is the UX side of the same fact).
 */
final class FunnelController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/cohorts/{cohort}/funnel
     */
    public function show(string $cohort): JsonResponse
    {
        $resolved = Cohort::query()->findOrFail($cohort);
        $this->authorize('viewAny', ApplicationSubmission::class);

        $viewed = LearningEvent::query()
            ->where('cohort_id', $resolved->id)
            ->where('event_name', 'application.viewed')
            ->count();

        $started = LearningEvent::query()
            ->where('cohort_id', $resolved->id)
            ->where('event_name', 'application.started')
            ->count();

        $submitted = ApplicationSubmission::query()
            ->where('cohort_id', $resolved->id)
            ->count();

        return response()->json([
            'data' => [
                // `viewed` is a raw page-hit count (every public apply GET, incl.
                // refreshes/bots — hence "approximate" in the UI). The clamp only
                // guards the rare case where a `viewed` emit was swallowed
                // best-effort while its `started` beacon landed (viewed < started).
                'viewed' => max($viewed, $started),
                'started' => $started,
                'submitted' => $submitted,
            ],
        ]);
    }
}
