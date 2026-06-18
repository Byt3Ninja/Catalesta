<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramRoleRequirement;
use App\Modules\Programs\Http\Requests\StoreProgramRoleRequirementRequest;
use App\Modules\Programs\Http\Resources\ProgramRoleRequirementResource;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class ProgramRoleRequirementController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/programs/{program}/role-requirements
     *
     * List all role requirements for the resolved tenant's program.
     */
    public function index(string $program): AnonymousResourceCollection
    {
        $prog = Program::query()->findOrFail($program);

        $this->authorize('update', $prog);

        $requirements = ProgramRoleRequirement::query()
            ->where('program_id', $prog->id)
            ->get();

        return ProgramRoleRequirementResource::collection($requirements);
    }

    /**
     * POST /api/v1/programs/{program}/role-requirements
     *
     * Create a role requirement on the program.
     */
    public function store(StoreProgramRoleRequirementRequest $request, AuditLogger $audit, string $program): JsonResponse
    {
        $prog = Program::query()->findOrFail($program);

        $this->authorize('update', $prog);

        /** @var array{role_key: string, min_count?: int, max_count?: int|null, is_required?: bool} $data */
        $data = $request->validated();

        $requirement = ProgramRoleRequirement::create([
            'program_id' => $prog->id,
            'role_key' => $data['role_key'],
            'min_count' => $data['min_count'] ?? 0,
            'max_count' => $data['max_count'] ?? null,
            'is_required' => $data['is_required'] ?? true,
        ]);

        $audit->record(
            'program.role_requirement.set',
            'program_role_requirement',
            $requirement->id,
            [],
            ['program_id' => $prog->id, 'role_key' => $requirement->role_key],
        );

        return (new ProgramRoleRequirementResource($requirement))
            ->response()
            ->setStatusCode(201);
    }
}
