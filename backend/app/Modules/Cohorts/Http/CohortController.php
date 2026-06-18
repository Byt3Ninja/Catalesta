<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Cohorts\Http\Requests\StoreCohortRequest;
use App\Modules\Cohorts\Http\Requests\UpdateCohortRequest;
use App\Modules\Cohorts\Http\Resources\CohortResource;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class CohortController extends Controller
{
    use AuthorizesRequests;

    /**
     * POST /api/v1/programs/{program}/cohorts
     *
     * Create a new cohort in Draft status under the given program.
     * Authorization is enforced in StoreCohortRequest::authorize() (program loaded
     * tenant-scoped; null → 403 before validation; then cohorts.manage checked).
     * organization_id is auto-stamped by BelongsToTenant trait.
     */
    public function store(StoreCohortRequest $request, AuditLogger $audit, string $program): JsonResponse
    {
        /** @var array{name: string, status?: string|null, capacity?: int|null, enrollment_opens_at?: string|null, enrollment_closes_at?: string|null, starts_at?: string|null, ends_at?: string|null, timeline?: array<string, mixed>|null} $data */
        $data = $request->validated();

        $cohort = Cohort::create(array_merge(
            $data,
            [
                'program_id' => $program,
                'status' => CohortStatus::Draft,
            ],
        ));

        $audit->record(
            'cohort.created',
            'cohort',
            $cohort->id,
            [],
            ['name' => $cohort->name, 'status' => $cohort->status->value, 'program_id' => $program],
        );

        return (new CohortResource($cohort))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/cohorts/{id}
     *
     * Show a single cohort. BelongsToTenant scope ensures cross-tenant ids 404.
     */
    public function show(string $id): CohortResource
    {
        $cohort = Cohort::query()->findOrFail($id);

        $this->authorize('view', $cohort);

        return new CohortResource($cohort);
    }

    /**
     * PATCH /api/v1/cohorts/{id}
     *
     * Update cohort fields. Authorization is enforced in UpdateCohortRequest::authorize()
     * (cohort loaded tenant-scoped; null → 403; then cohorts.manage checked).
     */
    public function update(UpdateCohortRequest $request, AuditLogger $audit, string $id): CohortResource
    {
        $cohort = Cohort::query()->findOrFail($id);

        /** @var array{name?: string, status?: string|null, capacity?: int|null, enrollment_opens_at?: string|null, enrollment_closes_at?: string|null, starts_at?: string|null, ends_at?: string|null, timeline?: array<string, mixed>|null} $data */
        $data = $request->validated();

        $before = $cohort->only([
            'name', 'status', 'capacity',
            'enrollment_opens_at', 'enrollment_closes_at', 'starts_at', 'ends_at', 'timeline',
        ]);

        // Use fill() so Eloquent's cast layer handles type coercion (string→Carbon, string→enum, etc.)
        $cohort->fill($data);

        $cohort->save();

        $after = $cohort->only([
            'name', 'status', 'capacity',
            'enrollment_opens_at', 'enrollment_closes_at', 'starts_at', 'ends_at', 'timeline',
        ]);

        $audit->record(
            'cohort.updated',
            'cohort',
            $cohort->id,
            $before,
            $after,
        );

        return new CohortResource($cohort);
    }
}
