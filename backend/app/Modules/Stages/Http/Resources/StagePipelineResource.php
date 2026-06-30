<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Resources;

use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Translates a StagePipeline (+ its versions relationship) → the FE stagePipelineSchema shape.
 *
 * Requires versions to be loaded: ->load('versions') or ->with('versions').
 * current_draft_version_id is always null in Phase 1 (authoring not implemented, ADR-0011).
 *
 * @property string $id
 * @property string $program_id
 * @property string $name
 * @property Carbon $created_at
 * @property Collection<int, StagePipelineVersion> $versions
 */
final class StagePipelineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $versions = $this->versions; // requires ->load('versions')
        $published = $versions->filter(fn (StagePipelineVersion $v) => $v->status->value === 'published')
            ->sortBy('version_number')->values();

        return [
            'pipeline_id' => $this->id,       // FE: pipeline_id (not id)
            'program_id' => $this->program_id,
            'name' => $this->name,
            'latest_version' => (int) ($published->max('version_number') ?? 0),
            'published_version_ids' => array_values($published->pluck('id')->map(fn (mixed $id) => (string) $id)->all()),
            'current_draft_version_id' => null,            // Phase 1: authoring not implemented (ADR-0011)
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
