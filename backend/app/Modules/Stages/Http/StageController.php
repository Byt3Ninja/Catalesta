<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Application\PublishStageVersion;
use App\Modules\Stages\Application\ReorderStages;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Modules\Stages\Http\Requests\ReorderStagesRequest;
use App\Modules\Stages\Http\Requests\StoreStageRequest;
use App\Modules\Stages\Http\Requests\UpdateStageRequest;
use App\Modules\Stages\Http\Resources\StageResource;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class StageController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/programs/{program}/stages
     *
     * List all stages for a program, ordered by order_index.
     */
    public function index(string $program): AnonymousResourceCollection
    {
        $prog = Program::query()->findOrFail($program);

        $this->authorize('viewAny', ProgramStage::class);

        $stages = ProgramStage::query()
            ->where('program_id', $prog->id)
            ->orderBy('order_index')
            ->with('versions')
            ->get();

        return StageResource::collection($stages);
    }

    /**
     * POST /api/v1/programs/{program}/stages
     *
     * Create a new stage + an initial draft version.
     */
    public function store(StoreStageRequest $request, AuditLogger $audit, string $program): JsonResponse
    {
        $prog = Program::query()->findOrFail($program);

        /** @var array{key: string, name: string, type: string, parallel_group?: string|null, config?: array<string, mixed>|null} $data */
        $data = $request->validated();

        $nextOrderIndex = ProgramStage::query()
            ->where('program_id', $prog->id)
            ->count();

        $stage = ProgramStage::create([
            'program_id' => $prog->id,
            'organization_id' => $prog->organization_id,
            'key' => $data['key'],
            'name' => $data['name'],
            'type' => StageType::from($data['type']),
            'order_index' => $nextOrderIndex,
            'parallel_group' => $data['parallel_group'] ?? null,
        ]);

        StageVersion::create([
            'program_stage_id' => $stage->id,
            'organization_id' => $prog->organization_id,
            'status' => 'draft',
            'version_number' => 0,
            'config' => $data['config'] ?? null,
        ]);

        $stage->load('versions');

        $audit->record(
            'stage.created',
            'program_stage',
            $stage->id,
            [],
            ['key' => $stage->key, 'name' => $stage->name, 'type' => $stage->type->value],
        );

        return (new StageResource($stage))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/stages/{id}
     *
     * Update the stage name/parallel_group and/or current draft version's config.
     * If the current draft has already been published, attempting to update config → 422.
     */
    public function update(UpdateStageRequest $request, AuditLogger $audit, string $id): JsonResponse
    {
        $stage = ProgramStage::query()->findOrFail($id);

        /** @var array{name?: string, parallel_group?: string|null, config?: array<string, mixed>|null} $data */
        $data = $request->validated();

        if (isset($data['name'])) {
            $stage->name = $data['name'];
        }

        if (array_key_exists('parallel_group', $data)) {
            $stage->parallel_group = $data['parallel_group'];
        }

        $stage->save();

        // If caller wants to update config, we need to find the current draft version.
        if (array_key_exists('config', $data)) {
            /** @var StageVersion|null $draftVersion */
            $draftVersion = StageVersion::query()
                ->where('program_stage_id', $stage->id)
                ->where('status', 'draft')
                ->latest()
                ->first();

            if ($draftVersion === null) {
                return response()->json([
                    'message' => 'Cannot update config: the current stage version is published. Create a new draft to make changes.',
                    'errors' => ['config' => ['The published stage version is immutable. A new draft version is required to update config.']],
                ], 422);
            }

            try {
                $draftVersion->config = $data['config'];
                $draftVersion->save();
            } catch (VersionStateException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['config' => ['The published stage version is immutable. A new draft version is required to update config.']],
                ], 422);
            }
        }

        $stage->load('versions');

        return (new StageResource($stage))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/v1/stages/{id}/publish
     *
     * Publish the stage's current draft version.
     */
    public function publish(PublishStageVersion $service, string $id): JsonResponse
    {
        $stage = ProgramStage::query()->findOrFail($id);

        $this->authorize('publish', $stage);

        /** @var StageVersion|null $draftVersion */
        $draftVersion = StageVersion::query()
            ->where('program_stage_id', $stage->id)
            ->where('status', 'draft')
            ->latest()
            ->first();

        if ($draftVersion === null) {
            return response()->json([
                'message' => 'No draft version found to publish.',
                'errors' => ['stage' => ['This stage has no draft version to publish.']],
            ], 422);
        }

        $service->handle($draftVersion);

        $stage->refresh()->load('versions');

        return (new StageResource($stage))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/v1/programs/{program}/stages/reorder
     *
     * Reorder stages by providing all stage ids in the desired order.
     */
    public function reorder(ReorderStagesRequest $request, ReorderStages $service, AuditLogger $audit, string $program): JsonResponse
    {
        $prog = Program::query()->findOrFail($program);

        /** @var array{stage_ids: array<int, string>} $data */
        $data = $request->validated();

        $service->handle($prog, $data['stage_ids']);

        $audit->record(
            'stage.reordered',
            'program',
            $prog->id,
            [],
            ['stage_ids' => $data['stage_ids']],
        );

        return response()->json(['message' => 'Stages reordered successfully.']);
    }
}
