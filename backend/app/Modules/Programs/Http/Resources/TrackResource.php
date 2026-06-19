<?php

declare(strict_types=1);

namespace App\Modules\Programs\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $program_id
 * @property-read string $key
 * @property-read string $name
 * @property-read string|null $description
 * @property-read int $order_index
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class TrackResource extends JsonResource
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
            'description' => $this->description,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
