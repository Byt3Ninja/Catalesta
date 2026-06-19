<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http;

use App\Modules\Programs\Application\CloneProgram;
use App\Modules\Programs\Application\PublishProgram;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Programs\Http\Requests\StoreProgramRequest;
use App\Modules\Programs\Http\Requests\UpdateProgramRequest;
use App\Modules\Programs\Http\Resources\ProgramResource;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class ProgramController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/programs
     *
     * List all programs for the resolved tenant.
     * BelongsToTenant global scope applies automatically.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Program::class);

        $programs = Program::all();

        return ProgramResource::collection($programs);
    }

    /**
     * POST /api/v1/programs
     *
     * Create a new program in Draft status.
     * organization_id is auto-stamped by BelongsToTenant trait.
     */
    public function store(StoreProgramRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', Program::class);

        /** @var array{name: string, description?: string|null, settings?: array<string, mixed>|null} $data */
        $data = $request->validated();

        $program = Program::create(array_merge(
            $data,
            ['status' => ProgramStatus::Draft],
        ));

        $audit->record(
            'program.created',
            'program',
            $program->id,
            [],
            ['name' => $program->name, 'status' => $program->status->value],
        );

        return (new ProgramResource($program))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/programs/{id}
     *
     * Show a single program. BelongsToTenant scope ensures cross-tenant ids 404.
     */
    public function show(string $id): ProgramResource
    {
        $program = Program::query()->findOrFail($id);

        $this->authorize('view', $program);

        return new ProgramResource($program);
    }

    /**
     * PATCH /api/v1/programs/{id}
     *
     * Update program name, description, and/or settings.
     * Programs are NOT immutable — PATCH works on Published programs too.
     */
    public function update(UpdateProgramRequest $request, AuditLogger $audit, string $id): ProgramResource
    {
        $program = Program::query()->findOrFail($id);

        $this->authorize('update', $program);

        /** @var array{name?: string, description?: string|null, settings?: array<string, mixed>|null} $data */
        $data = $request->validated();

        $before = $program->only(['name', 'description', 'settings']);

        if (isset($data['name'])) {
            $program->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $program->description = $data['description'];
        }

        if (array_key_exists('settings', $data)) {
            $program->settings = $data['settings'];
        }

        $program->save();

        $after = $program->only(['name', 'description', 'settings']);

        $audit->record(
            'program.updated',
            'program',
            $program->id,
            $before,
            $after,
        );

        return new ProgramResource($program);
    }

    /**
     * POST /api/v1/programs/{id}/publish
     *
     * Transition the program to Published status.
     * Requires programs.publish permission.
     */
    public function publish(PublishProgram $service, string $id): ProgramResource
    {
        $program = Program::query()->findOrFail($id);

        $this->authorize('publish', $program);

        $program = $service->handle($program);

        return new ProgramResource($program);
    }

    /**
     * POST /api/v1/programs/{id}/clone
     *
     * Deep-copy a program into a new DRAFT program.
     * Requires programs.manage permission.
     */
    public function clone(Request $request, CloneProgram $service, string $id): JsonResponse
    {
        $program = Program::query()->findOrFail($id);

        $this->authorize('clone', $program);

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        /** @var string $name */
        $name = $request->input('name');

        $clone = $service->handle($program, $name);

        return (new ProgramResource($clone))
            ->response()
            ->setStatusCode(201);
    }
}
