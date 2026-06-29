<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http;

use App\Modules\Forms\Domain\Models\FormVersion;
use App\Modules\Forms\Http\Resources\FormVersionResource;
use Illuminate\Routing\Controller;

final class FormVersionController extends Controller
{
    public function show(string $id): FormVersionResource
    {
        $version = FormVersion::query()->findOrFail($id);

        return new FormVersionResource($version);
    }
}
