<?php

declare(strict_types=1);

namespace App\Modules\Stages\Application;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Domain\Exceptions\StagePipelineNotPublishableException;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageDependency;
use App\Modules\Stages\Domain\Models\StagePipeline;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionPublisher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots a program's PUBLISHED stage graph into an immutable, content-addressed
 * StagePipelineVersion (ADR-0011 Phase 1). Reads the Stages engine only — never mutates it.
 */
final class PublishStagePipeline
{
    public function __construct(
        private readonly VersionPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws StagePipelineNotPublishableException when a stage lacks a published version, or there are no stages
     */
    public function handle(Program $program): StagePipelineVersion
    {
        /** @var Collection<int, ProgramStage> $stages */
        $stages = ProgramStage::query()
            ->where('program_id', $program->id)
            ->orderBy('order_index')
            ->get();

        if ($stages->isEmpty()) {
            throw new StagePipelineNotPublishableException('The program has no stages to publish.');
        }

        $unpublished = $stages->filter(fn (ProgramStage $s) => $s->current_published_version_id === null)
            ->map(fn (ProgramStage $s) => $s->key)->values()->all();
        if ($unpublished !== []) {
            throw new StagePipelineNotPublishableException(
                'Every stage must have a published version before the pipeline can be published. Unpublished: '.implode(', ', $unpublished)
            );
        }

        $snapshot = $this->buildSnapshot($program, $stages);
        $hash = hash('sha256', $this->canonicalJson($snapshot));

        $pipeline = StagePipeline::query()->firstOrCreate(
            ['program_id' => $program->id],
            ['name' => $program->name],
        );

        $created = false;
        $version = DB::transaction(function () use ($pipeline, $snapshot, $hash, &$created): StagePipelineVersion {
            /** @var StagePipelineVersion|null $existing */
            $existing = StagePipelineVersion::query()
                ->where('stage_pipeline_id', $pipeline->id)
                ->where('status', 'published')
                ->where('content_hash', $hash)
                ->first();
            if ($existing !== null) {
                return $existing; // idempotent republish — no duplicate row
            }

            $created = true;
            $version = StagePipelineVersion::create([
                'stage_pipeline_id' => $pipeline->id,
                'content_hash' => $hash,
                'snapshot' => $snapshot,
            ]);
            $this->publisher->publish($version); // version_number, Published, published_at
            $pipeline->update(['current_published_version_id' => $version->id]);

            return $version->refresh();
        });

        if ($created) {
            $this->audit->record(AuditAction::StagePipelinePublished->value, 'stage_pipeline_version', $version->id, [], [
                'content_hash' => $hash,
                'version_number' => $version->version_number,
            ]);
        }

        return $version;
    }

    /**
     * @param  Collection<int, ProgramStage>  $stages
     * @return array<string, mixed>
     */
    private function buildSnapshot(Program $program, Collection $stages): array
    {
        $transitions = StageTransition::query()->where('program_id', $program->id)->get();
        $stageIds = $stages->pluck('id')->all();
        $deps = StageDependency::query()->whereIn('program_stage_id', $stageIds)->get();

        $stageNodes = $stages->map(function (ProgramStage $stage) use ($transitions, $deps): array {
            /** @var StageVersion $pv */
            $pv = StageVersion::query()->findOrFail($stage->current_published_version_id);
            $rules = StageRule::query()->where('stage_version_id', $pv->id)
                ->get(['type', 'expression'])
                ->map(fn (StageRule $r) => ['type' => $r->type->value, 'expression' => $r->expression])->all();

            return [
                'stage_id' => $stage->id,
                'key' => $stage->key,
                'name' => $stage->name,
                'type' => $stage->type->value,            // backend-native (11-value)
                'order_index' => $stage->order_index,
                'stage_version_id' => $pv->id,
                'config' => $pv->config,
                'rules' => $rules,                         // native StageRule expressions
                'next_stage_ids' => $transitions->where('from_program_stage_id', $stage->id)
                    ->sortBy('order_index')->pluck('to_program_stage_id')->values()->all(),
                'depends_on_stage_ids' => $deps->where('program_stage_id', $stage->id)
                    ->pluck('depends_on_program_stage_id')->values()->all(),
            ];
        })->all();

        return ['program_id' => $program->id, 'stages' => $stageNodes];
    }

    /**
     * Stable canonical serialization (recursively key-sorted) for the content hash.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function canonicalJson(array $value): string
    {
        return json_encode($this->ksortRecursive($value), JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function ksortRecursive(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->ksortRecursive($v);
            }
        }

        return $value;
    }
}
