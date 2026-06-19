<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http;

use App\Modules\Programs\Application\CreateProgramFromTemplate;
use App\Modules\Programs\Application\SaveProgramAsTemplate;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramTemplate;
use App\Modules\Programs\Http\Resources\ProgramResource;
use App\Modules\Programs\Http\Resources\ProgramTemplateResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ProgramTemplateController extends Controller
{
    use AuthorizesRequests;

    /**
     * POST /api/v1/program-templates
     *
     * Save an existing program as a reusable template.
     * Reuses programs.manage permission (checked via 'create' gate on Program).
     */
    public function store(Request $request, SaveProgramAsTemplate $service): JsonResponse
    {
        $this->authorize('create', Program::class);

        $validated = $request->validate([
            'program_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        /** @var string $programId */
        $programId = $validated['program_id'];

        /** @var string $name */
        $name = $validated['name'];

        // Tenant-scoped lookup — cross-tenant ids yield 404 via BelongsToTenant scope
        $program = Program::query()->findOrFail($programId);

        $template = $service->handle($program, $name);

        return (new ProgramTemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/v1/program-templates/{templateId}/instantiate
     *
     * Materialize a new DRAFT program from a template blueprint.
     *
     * Uses an explicit tenant-scoped lookup rather than implicit route model binding
     * because SubstituteBindings runs before the 'tenant' middleware in the API group,
     * meaning BelongsToTenant scope is not yet active when bindings resolve.
     * Cross-tenant template ids → 404 via BelongsToTenant scope on the explicit query.
     */
    public function instantiate(
        Request $request,
        CreateProgramFromTemplate $service,
        string $templateId,
    ): JsonResponse {
        $this->authorize('create', Program::class);

        $programTemplate = ProgramTemplate::query()->findOrFail($templateId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        /** @var string $name */
        $name = $validated['name'];

        $program = $service->handle($programTemplate, $name);

        return (new ProgramResource($program))
            ->response()
            ->setStatusCode(201);
    }
}
