<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http;

use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Assessments\Http\Resources\ScoringModelVersionResource;
use Illuminate\Routing\Controller;

final class ScoringModelVersionController extends Controller
{
    public function show(string $id): ScoringModelVersionResource
    {
        $version = ScoringModelVersion::query()->findOrFail($id);

        return new ScoringModelVersionResource($version);
    }
}
