<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Resources;

use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Programs\Domain\Models\ProgramType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read ProgramStatus $status
 * @property-read ProgramType|null $type
 * @property-read string|null $description
 * @property-read array<string, mixed>|null $settings
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class ProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'type' => $this->type?->value,
            'description' => $this->description,
            'settings' => $this->settings,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
