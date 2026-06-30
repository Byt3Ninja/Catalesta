<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http;

use App\Modules\Cohorts\Application\BindCohortForm;
use App\Modules\Cohorts\Application\BindCohortStagePipeline;
use App\Modules\Cohorts\Application\OpenCohort;
use App\Modules\Cohorts\Domain\Exceptions\CohortStateException;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Cohorts\Http\Requests\BindCohortFormRequest;
use App\Modules\Cohorts\Http\Requests\BindCohortStagePipelineRequest;
use App\Modules\Cohorts\Http\Requests\StoreCohortRequest;
use App\Modules\Cohorts\Http\Requests\UpdateCohortRequest;
use App\Modules\Cohorts\Http\Resources\CohortResource;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class CohortController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/cohorts
     *
     * List the resolved tenant's cohorts for the operator Home (Story 1.5).
     * BelongsToTenant global scope does the isolation (AR-6) — no manual
     * organization_id filter. `submissions_count` drives Home's next action.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Cohort::class);

        $cohorts = Cohort::query()
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->get();

        return CohortResource::collection($cohorts);
    }

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

    /**
     * POST /api/v1/cohorts/{id}/open
     *
     * Transition a draft cohort (with a bound form) to Open status.
     * 409 when the cohort is not draft or has no form bound.
     * Cross-tenant cohort ids 404 via BelongsToTenant scope.
     */
    public function open(OpenCohort $service, string $id): JsonResponse
    {
        $cohort = Cohort::query()->findOrFail($id);
        $this->authorize('open', $cohort);

        try {
            $cohort = $service->handle($cohort);
        } catch (CohortStateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new CohortResource($cohort))->response()->setStatusCode(200);
    }

    /**
     * POST /api/v1/cohorts/{id}/bind-form
     *
     * Bind a published FormVersion to a draft cohort. Idempotent for the same
     * version; 409 when a different version is already bound or the cohort is
     * not in draft status. Cross-tenant cohort ids 404 via BelongsToTenant scope.
     */
    public function bindForm(BindCohortFormRequest $request, BindCohortForm $service, string $id): JsonResponse
    {
        $cohort = Cohort::query()->findOrFail($id);
        $this->authorize('bindForm', $cohort);

        /** @var array{form_version_id: string} $data */
        $data = $request->validated();

        try {
            $cohort = $service->handle($cohort, $data['form_version_id']);
        } catch (CohortStateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new CohortResource($cohort))->response()->setStatusCode(200);
    }

    /**
     * POST /api/v1/cohorts/{id}/bind-stage-pipeline
     *
     * Bind a published StagePipelineVersion to a draft cohort. Idempotent for the
     * same version; 409 when a different version is already bound or the cohort is
     * not in draft status. Cross-tenant cohort ids 404 via BelongsToTenant scope.
     */
    public function bindStagePipeline(BindCohortStagePipelineRequest $request, BindCohortStagePipeline $service, string $id): JsonResponse
    {
        $cohort = Cohort::query()->findOrFail($id);
        $this->authorize('bindStagePipeline', $cohort);

        /** @var array{stage_pipeline_version_id: string} $data */
        $data = $request->validated();

        try {
            $cohort = $service->handle($cohort, $data['stage_pipeline_version_id']);
        } catch (CohortStateException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new CohortResource($cohort))->response()->setStatusCode(200);
    }
}
