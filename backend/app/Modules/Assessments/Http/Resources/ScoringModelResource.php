<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Http\Resources;

use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $program_id
 * @property string $name
 * @property Collection<int, ScoringModelVersion> $versions
 * @property Carbon $created_at
 */
final class ScoringModelResource extends JsonResource
{
    /**
     * @return array{
     *     model_id: string,
     *     program_id: string,
     *     name: string,
     *     latest_version: int,
     *     published_version_ids: list<string>,
     *     current_draft_version_id: string|null,
     *     created_at: string,
     * }
     */
    public function toArray(Request $request): array
    {
        $versions = $this->versions; // requires ->load('versions')
        $published = $versions
            ->filter(fn (ScoringModelVersion $v) => $v->status->value === 'published')
            ->sortBy('version_number')
            ->values();
        $draft = $versions->first(fn (ScoringModelVersion $v) => $v->status->value === 'draft');

        return [
            'model_id' => $this->id,
            'program_id' => $this->program_id,
            'name' => $this->name,
            'latest_version' => (int) ($published->max('version_number') ?? 0),
            'published_version_ids' => array_values(
                $published->pluck('id')->map(fn (mixed $id) => (string) $id)->all()
            ),
            'current_draft_version_id' => $draft?->id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
