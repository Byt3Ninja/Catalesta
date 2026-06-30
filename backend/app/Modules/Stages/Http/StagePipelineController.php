<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Application\PublishStagePipeline;
use App\Modules\Stages\Domain\Exceptions\StagePipelineNotPublishableException;
use App\Modules\Stages\Domain\Models\StagePipeline;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Modules\Stages\Http\Resources\StagePipelineResource;
use App\Modules\Stages\Http\Resources\StagePipelineVersionResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class StagePipelineController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/programs/{program}/stage-pipelines
     *
     * List the program's pipeline (0 until first publish, 1 thereafter — one per program).
     */
    public function index(string $program): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StagePipeline::class);

        $prog = Program::query()->findOrFail($program);

        $pipelines = StagePipeline::query()
            ->where('program_id', $prog->id)
            ->with('versions')
            ->get();

        return StagePipelineResource::collection($pipelines);
    }

    /**
     * GET /api/v1/stage-pipelines/{id}
     *
     * Show a single pipeline with version metadata.
     * BelongsToTenant scope on StagePipeline enforces cross-tenant 404.
     */
    public function show(string $id): JsonResponse
    {
        $pipeline = StagePipeline::query()->with('versions')->findOrFail($id);

        $this->authorize('view', $pipeline);

        return (new StagePipelineResource($pipeline))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * GET /api/v1/stage-pipelines/{pipeline}/versions
     *
     * List all versions for a pipeline, ordered by version_number ascending.
     */
    public function versions(string $pipeline): AnonymousResourceCollection
    {
        $p = StagePipeline::query()->findOrFail($pipeline);

        $this->authorize('view', $p);

        $versions = StagePipelineVersion::query()
            ->where('stage_pipeline_id', $p->id)
            ->orderBy('version_number')
            ->get();

        return StagePipelineVersionResource::collection($versions);
    }

    /**
     * POST /api/v1/programs/{program}/stage-pipelines/publish
     *
     * Snapshot the program's published stage graph into an immutable StagePipelineVersion.
     * Idempotent: republishing an identical graph returns the existing version (200).
     */
    public function publish(PublishStagePipeline $service, string $program): JsonResponse
    {
        $this->authorize('publish', StagePipeline::class);

        $prog = Program::query()->findOrFail($program);

        try {
            $version = $service->handle($prog);
        } catch (StagePipelineNotPublishableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['stages' => [$e->getMessage()]],
            ], 422);
        }

        return (new StagePipelineVersionResource($version))
            ->response()
            ->setStatusCode(200);
    }
}
