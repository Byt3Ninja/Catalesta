<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http;

use App\Modules\Stages\Application\AddStageDependency;
use App\Modules\Stages\Application\RemoveStageDependency;
use App\Modules\Stages\Domain\Exceptions\InvalidStageDependencyException;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageDependency;
use App\Modules\Stages\Http\Requests\StoreStageDependencyRequest;
use App\Modules\Stages\Http\Resources\StageDependencyResource;
use App\Shared\Audit\AuditLogger;
use App\Shared\Support\CorrelationId;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class StageDependencyController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/programs/{program}/stages/{stage}/dependencies
     *
     * List all prerequisite dependencies for a stage.
     * BelongsToTenant global scope ensures cross-tenant program/stage → 404.
     */
    public function index(string $program, string $stage): AnonymousResourceCollection
    {
        // Tenant-scoped findOrFail ensures cross-tenant ids 404
        $programStage = ProgramStage::query()
            ->where('program_id', $program)
            ->findOrFail($stage);

        $this->authorize('manageDependencies', $programStage);

        $dependencies = StageDependency::query()
            ->where('program_stage_id', $programStage->id)
            ->get();

        return StageDependencyResource::collection($dependencies);
    }

    /**
     * POST /api/v1/programs/{program}/stages/{stage}/dependencies
     *
     * Add a prerequisite dependency to a stage.
     * Rejects self-edges, cross-program edges, and cycles with 422.
     */
    public function store(
        StoreStageDependencyRequest $request,
        AddStageDependency $service,
        AuditLogger $audit,
        string $program,
        string $stage,
    ): JsonResponse {
        // Tenant-scoped findOrFail ensures cross-tenant ids 404
        $programStage = ProgramStage::query()
            ->where('program_id', $program)
            ->findOrFail($stage);

        $this->authorize('manageDependencies', $programStage);

        /** @var string $dependsOnId */
        $dependsOnId = $request->validated('depends_on_program_stage_id');

        try {
            $dependency = $service->handle($programStage, $dependsOnId);
        } catch (InvalidStageDependencyException $e) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_stage_dependency',
                    'message' => $e->getMessage(),
                    'correlation_id' => CorrelationId::get(),
                ],
            ], 422);
        }

        $audit->record(
            'stage_dependency.added',
            'stage_dependency',
            $dependency->id,
            [],
            [
                'program_stage_id' => $dependency->program_stage_id,
                'depends_on_program_stage_id' => $dependency->depends_on_program_stage_id,
            ],
        );

        return (new StageDependencyResource($dependency))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /api/v1/stage-dependencies/{id}
     *
     * Remove a stage dependency.
     * BelongsToTenant global scope ensures cross-tenant ids 404.
     */
    public function destroy(
        RemoveStageDependency $service,
        AuditLogger $audit,
        string $id,
    ): JsonResponse {
        $dependency = StageDependency::query()->findOrFail($id);

        $programStage = ProgramStage::query()->findOrFail($dependency->program_stage_id);

        $this->authorize('manageDependencies', $programStage);

        $audit->record(
            'stage_dependency.removed',
            'stage_dependency',
            $dependency->id,
            [
                'program_stage_id' => $dependency->program_stage_id,
                'depends_on_program_stage_id' => $dependency->depends_on_program_stage_id,
            ],
            [],
        );

        $service->handle($dependency);

        return response()->json(null, 204);
    }
}
