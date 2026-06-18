<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramPolicyRecord;
use App\Modules\Programs\Http\Requests\StoreProgramPolicyRequest;
use App\Modules\Programs\Http\Resources\ProgramPolicyResource;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class ProgramPolicyController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/programs/{program}/policies
     *
     * List all policies for the resolved tenant's program.
     * BelongsToTenant scope on Program ensures cross-tenant program IDs 404.
     */
    public function index(string $program): AnonymousResourceCollection
    {
        $prog = Program::query()->findOrFail($program);

        $this->authorize('update', $prog);

        $policies = ProgramPolicyRecord::query()
            ->where('program_id', $prog->id)
            ->get();

        return ProgramPolicyResource::collection($policies);
    }

    /**
     * POST /api/v1/programs/{program}/policies
     *
     * Set a policy key/value on the program.
     * Duplicate key for same program → 422 via FormRequest Rule::unique.
     */
    public function store(StoreProgramPolicyRequest $request, AuditLogger $audit, string $program): JsonResponse
    {
        $prog = Program::query()->findOrFail($program);

        $this->authorize('update', $prog);

        /** @var array{key: string, value: mixed} $data */
        $data = $request->validated();

        $policy = ProgramPolicyRecord::create([
            'program_id' => $prog->id,
            'key' => $data['key'],
            'value' => $data['value'],
        ]);

        $audit->record(
            'program.policy.set',
            'program_policy',
            $policy->id,
            [],
            ['program_id' => $prog->id, 'key' => $policy->key],
        );

        return (new ProgramPolicyResource($policy))
            ->response()
            ->setStatusCode(201);
    }
}
