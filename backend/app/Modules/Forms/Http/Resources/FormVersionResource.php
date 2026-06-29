<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Resources;

use App\Shared\Versioning\VersionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $form_id
 * @property int $version_number
 * @property VersionStatus $status
 * @property array<int, array<string, mixed>> $definition
 * @property Carbon $created_at
 * @property Carbon|null $published_at
 */
final class FormVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'version' => $this->version_number,
            'status' => $this->status->value,
            'fields' => $this->definition,
            'created_at' => $this->created_at->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
