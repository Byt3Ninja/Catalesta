<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http;

use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Modules\Stages\Http\Resources\StagePipelineVersionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class StagePipelineVersionController extends Controller
{
    /**
     * GET /api/v1/stage-pipeline-versions/{id}
     *
     * Show a single pipeline version with FE-mapped stage shape.
     * BelongsToTenant global scope on StagePipelineVersion enforces cross-tenant 404.
     */
    public function show(string $id): JsonResponse
    {
        $version = StagePipelineVersion::query()->findOrFail($id);

        return (new StagePipelineVersionResource($version))
            ->response()
            ->setStatusCode(200);
    }
}
