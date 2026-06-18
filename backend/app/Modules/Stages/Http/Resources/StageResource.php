<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Resources;

use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $program_id
 * @property string $key
 * @property string $name
 * @property StageType $type
 * @property int $order_index
 * @property string|null $parallel_group
 * @property string|null $current_published_version_id
 * @property Collection<int, StageVersion> $versions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class StageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_id' => $this->program_id,
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type->value,
            'order_index' => $this->order_index,
            'parallel_group' => $this->parallel_group,
            'current_published_version_id' => $this->current_published_version_id,
            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn (StageVersion $v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'status' => $v->status->value,
                'published_at' => $v->published_at?->toIso8601String(),
            ])->values()->all()),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
