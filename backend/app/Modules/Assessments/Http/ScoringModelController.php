<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http;

use App\Modules\Assessments\Application\CreateScoringModel;
use App\Modules\Assessments\Application\ForkScoringModelDraft;
use App\Modules\Assessments\Application\PublishScoringModel;
use App\Modules\Assessments\Application\SaveScoringModelDraft;
use App\Modules\Assessments\Domain\Exceptions\InvalidCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoDraftException;
use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Assessments\Http\Requests\ForkScoringModelDraftRequest;
use App\Modules\Assessments\Http\Requests\SaveScoringModelDraftRequest;
use App\Modules\Assessments\Http\Requests\StoreScoringModelRequest;
use App\Modules\Assessments\Http\Resources\ScoringModelResource;
use App\Modules\Assessments\Http\Resources\ScoringModelVersionResource;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class ScoringModelController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /programs/{program}/scoring-models
     * Lists all scoring models for the given program (tenant-scoped by BelongsToTenant).
     * Uses manual findOrFail (not route model binding) to match codebase convention.
     */
    public function index(string $program): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ScoringModel::class);

        $prog = Program::query()->findOrFail($program);

        $models = ScoringModel::query()
            ->where('program_id', $prog->id)
            ->with('versions')
            ->orderByDesc('created_at')
            ->get();

        return ScoringModelResource::collection($models);
    }

    /**
     * POST /programs/{program}/scoring-models
     * Creates a scoring model for the program + seeds an empty draft version.
     * Uses manual findOrFail (not route model binding) to match codebase convention.
     */
    public function store(
        StoreScoringModelRequest $request,
        CreateScoringModel $service,
        string $program,
    ): JsonResponse {
        $this->authorize('create', ScoringModel::class);

        $prog = Program::query()->findOrFail($program);

        /** @var array{name: string} $data */
        $data = $request->validated();
        $model = $service->handle($prog, $data['name']);

        return (new ScoringModelResource($model))->response()->setStatusCode(201);
    }

    /**
     * GET /scoring-models/{id}
     */
    public function show(string $id): ScoringModelResource
    {
        $model = ScoringModel::query()->with('versions')->findOrFail($id);
        $this->authorize('view', $model);

        return new ScoringModelResource($model);
    }

    /**
     * GET /scoring-models/{id}/versions
     */
    public function versions(string $id): AnonymousResourceCollection
    {
        $model = ScoringModel::query()->findOrFail($id);
        $this->authorize('view', $model);

        $versions = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->orderByDesc('version_number')
            ->get();

        return ScoringModelVersionResource::collection($versions);
    }

    /**
     * PATCH /scoring-models/{id}/draft
     * Upserts criteria onto the current draft version.
     * Reads criteria via input() NOT validated() to avoid nested-key stripping.
     */
    public function saveDraft(
        SaveScoringModelDraftRequest $request,
        SaveScoringModelDraft $service,
        string $id,
    ): JsonResponse {
        $model = ScoringModel::query()->findOrFail($id);
        $this->authorize('update', $model);

        /** @var array<int, array<string, mixed>> $criteria */
        $criteria = $request->input('criteria', []);

        try {
            $version = $service->handle($model, $criteria);
        } catch (NoDraftException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (InvalidCriteriaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['criteria' => [$e->getMessage()]],
            ], 422);
        }

        return (new ScoringModelVersionResource($version))->response()->setStatusCode(200);
    }

    /**
     * POST /scoring-models/{id}/publish
     * Publishes the draft (idempotent; content-hash deduplicates).
     * 409 → no draft; 422 → draft has no criteria.
     */
    public function publish(PublishScoringModel $service, string $id): JsonResponse
    {
        $model = ScoringModel::query()->findOrFail($id);
        $this->authorize('publish', $model);

        try {
            $version = $service->handle($model);
        } catch (NoDraftException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (NoCriteriaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['criteria' => [$e->getMessage()]],
            ], 422);
        }

        return (new ScoringModelVersionResource($version))->response()->setStatusCode(200);
    }

    /**
     * POST /scoring-models/{id}/fork
     * Creates a new draft seeded from a published version.
     * Returns 201 for a new draft; 200 for an existing draft returned unchanged.
     * ModelNotFoundException (invalid/unpublished from_version_id) → 404.
     */
    public function fork(
        ForkScoringModelDraftRequest $request,
        ForkScoringModelDraft $service,
        string $id,
    ): JsonResponse {
        $model = ScoringModel::query()->findOrFail($id);
        $this->authorize('update', $model);

        /** @var array{from_version_id: string} $data */
        $data = $request->validated();

        $isNew = $model->draftVersion() === null;
        $draft = $service->handle($model, $data['from_version_id']); // ModelNotFoundException → 404

        $status = $isNew ? 201 : 200;

        return (new ScoringModelVersionResource($draft))->response()->setStatusCode($status);
    }
}
