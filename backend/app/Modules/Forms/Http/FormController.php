<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http;

use App\Modules\Forms\Application\CreateForm;
use App\Modules\Forms\Application\ForkFormDraft;
use App\Modules\Forms\Application\PublishForm;
use App\Modules\Forms\Application\SaveFormDraft;
use App\Modules\Forms\Domain\Exceptions\InvalidFormDefinitionException;
use App\Modules\Forms\Domain\Exceptions\NoDraftToPublishException;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Modules\Forms\Http\Requests\ForkFormDraftRequest;
use App\Modules\Forms\Http\Requests\SaveFormDraftRequest;
use App\Modules\Forms\Http\Requests\StoreFormRequest;
use App\Modules\Forms\Http\Resources\FormResource;
use App\Modules\Forms\Http\Resources\FormVersionResource;
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

    public function saveDraft(SaveFormDraftRequest $request, SaveFormDraft $service, string $id): JsonResponse
    {
        $form = Form::query()->findOrFail($id);
        $this->authorize('update', $form);

        // Structural rules (fields present + array, fields.*.type string) already enforced
        // by SaveFormDraftRequest before this method runs.
        /** @var array<int, array<string, mixed>> $fields */
        $fields = $request->input('fields', []);

        try {
            $version = $service->handle($form, $fields);
        } catch (NoDraftToPublishException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (InvalidFormDefinitionException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['fields' => [$e->getMessage()]]], 422);
        }

        return (new FormVersionResource($version))->response()->setStatusCode(200);
    }

    public function publish(PublishForm $service, string $id): JsonResponse
    {
        $form = Form::query()->findOrFail($id);
        $this->authorize('publish', $form);

        try {
            $version = $service->handle($form);
        } catch (NoDraftToPublishException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return (new FormVersionResource($version))->response()->setStatusCode(200);
    }

    public function fork(ForkFormDraftRequest $request, ForkFormDraft $service, string $id): JsonResponse
    {
        $form = Form::query()->findOrFail($id);
        $this->authorize('update', $form);

        /** @var array{from_version_id: string} $data */
        $data = $request->validated();
        $draft = $service->handle($form, $data['from_version_id']); // ModelNotFoundException → 404

        return (new FormVersionResource($draft))->response()->setStatusCode(201);
    }

    public function versions(string $form): AnonymousResourceCollection
    {
        $model = Form::query()->findOrFail($form);
        $this->authorize('view', $model);

        $versions = FormVersion::query()
            ->where('form_id', $model->id)
            ->orderByDesc('version_number')
            ->get();

        return FormVersionResource::collection($versions);
    }
}
