<?php

declare(strict_types=1);

namespace App\Modules\Forms\Http\Resources;

use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $name
 * @property Collection<int, FormVersion> $versions
 */
final class FormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $versions = $this->versions; // requires ->load('versions')
        $published = $versions->filter(fn (FormVersion $v) => $v->status->value === 'published')
            ->sortBy('version_number')->values();
        $draft = $versions->first(fn (FormVersion $v) => $v->status->value === 'draft');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => null,
            'latest_version' => (int) ($published->max('version_number') ?? 0),
            'published_version_ids' => $published->pluck('id')->all(),
            'current_draft_version_id' => $draft?->id,
        ];
    }
}
