<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read array<string, mixed>|null $branding
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
final class OrganizationResource extends JsonResource
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
            'branding' => $this->branding,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
