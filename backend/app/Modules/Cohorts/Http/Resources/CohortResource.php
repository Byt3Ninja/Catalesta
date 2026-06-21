<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Http\Resources;

use App\Modules\Cohorts\Domain\Models\CohortStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $program_id
 * @property-read string $name
 * @property-read string $slug
 * @property-read CohortStatus $status
 * @property-read int|null $capacity
 * @property-read Carbon|null $enrollment_opens_at
 * @property-read Carbon|null $enrollment_closes_at
 * @property-read Carbon|null $starts_at
 * @property-read Carbon|null $ends_at
 * @property-read array<string, mixed>|null $timeline
 * @property-read int|null $submissions_count
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class CohortResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'program_id' => $this->program_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'capacity' => $this->capacity,
            'enrollment_opens_at' => $this->enrollment_opens_at?->toIso8601String(),
            'enrollment_closes_at' => $this->enrollment_closes_at?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timeline' => $this->timeline,
            // Present only on the list (withCount); never null on show/store.
            'submissions_count' => $this->whenCounted('submissions'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
