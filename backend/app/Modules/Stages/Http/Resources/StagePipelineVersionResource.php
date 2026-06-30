<?php

declare(strict_types=1);

namespace App\Modules\Stages\Http\Resources;

use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Translates a StagePipelineVersion snapshot → the FE-consumable shape (ADR-0011 Phase 1).
 *
 * Phase 1 is a structural preview: entry_rule / exit_rule / parallel_group are always null.
 * The snapshot keeps the true 11-value backend StageType; the resource maps it to the
 * 5-value FE vocabulary (stageTypeSchema in frontend/src/schemas/stages.ts).
 *
 * @property string $id
 * @property string $stage_pipeline_id
 * @property int $version_number
 * @property VersionStatus $status
 * @property array<string, mixed> $snapshot
 * @property Carbon $created_at
 * @property Carbon|null $published_at
 */
final class StagePipelineVersionResource extends JsonResource
{
    /**
     * Backend StageType (11 values) → FE stageTypeSchema vocabulary (5 values).
     * Unlisted backend types map to the catch-all 'task'.
     *
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'review' => 'review',
        'interview' => 'interview',
        'evaluation' => 'decision',
        'graduation' => 'decision',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<int, array<string, mixed>> $stages */
        $stages = $this->snapshot['stages'] ?? [];

        return [
            'version_id' => $this->id,                            // FE: version_id (not id)
            'pipeline_id' => $this->stage_pipeline_id,
            'version' => $this->version_number,
            'status' => $this->status->value,
            'stages' => array_map(fn (array $s): array => [
                'stage_id' => $s['stage_id'],
                'name' => $s['name'],
                'type' => self::TYPE_MAP[$s['type']] ?? 'task',
                'entry_rule' => null,   // Phase 1: structural preview only (ADR-0011)
                'exit_rule' => null,
                'next_stage_ids' => $s['next_stage_ids'] ?? [],
                'depends_on_stage_ids' => $s['depends_on_stage_ids'] ?? [],
                'parallel_group' => null,   // Phase 1: parallel groups not implemented
                'order' => $s['order_index'],
            ], $stages),
            'created_at' => $this->created_at->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
