<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $program_id
 * @property-read string $role_key
 * @property-read int $min_count
 * @property-read int|null $max_count
 * @property-read bool $is_required
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class ProgramRoleRequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_id' => $this->program_id,
            'role_key' => $this->role_key,
            'min_count' => $this->min_count,
            'max_count' => $this->max_count,
            'is_required' => $this->is_required,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
