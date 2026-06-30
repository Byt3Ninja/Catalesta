<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Resources;

use App\Shared\Versioning\VersionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $scoring_model_id
 * @property int $version_number
 * @property VersionStatus $status
 * @property array<int, array<string, mixed>> $criteria
 * @property Carbon $created_at
 * @property Carbon|null $published_at
 */
final class ScoringModelVersionResource extends JsonResource
{
    /**
     * @return array{
     *     version_id: string,
     *     model_id: string,
     *     version: int,
     *     status: string,
     *     criteria: list<array<string, mixed>>,
     *     created_at: string,
     *     published_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'version_id' => $this->id,
            'model_id' => $this->scoring_model_id,
            'version' => (int) $this->version_number,
            'status' => $this->status->value,
            'criteria' => array_values((array) ($this->criteria ?? [])),
            'created_at' => $this->created_at->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
