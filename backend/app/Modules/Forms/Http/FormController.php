<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http;

use App\Modules\Forms\Application\CreateForm;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Http\Requests\StoreFormRequest;
use App\Modules\Forms\Http\Resources\FormResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class FormController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Form::class);

        $forms = Form::query()->with('versions')->orderByDesc('created_at')->get();

        return FormResource::collection($forms);
    }

    public function store(StoreFormRequest $request, CreateForm $service): JsonResponse
    {
        $this->authorize('create', Form::class);

        /** @var array{name: string} $data */
        $data = $request->validated();
        $form = $service->handle($data['name']);

        return (new FormResource($form))->response()->setStatusCode(201);
    }

    public function show(string $id): FormResource
    {
        $form = Form::query()->with('versions')->findOrFail($id);
        $this->authorize('view', $form);

        return new FormResource($form);
    }
}
