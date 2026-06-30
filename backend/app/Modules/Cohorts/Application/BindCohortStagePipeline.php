<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Application;

use App\Modules\Cohorts\Domain\Exceptions\CohortStateException;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Stages\Domain\Models\StagePipelineVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class BindCohortStagePipeline
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws CohortStateException on a non-draft cohort or a conflicting bound version
     * @throws ModelNotFoundException when no published version with that id exists in the tenant
     */
    public function handle(Cohort $cohort, string $versionId): Cohort
    {
        if ($cohort->status !== CohortStatus::Draft) {
            throw new CohortStateException('A stage pipeline can only be bound while the cohort is a draft.');
        }

        $version = StagePipelineVersion::query()->where('status', 'published')->findOrFail($versionId);

        if ($cohort->stage_pipeline_version_id === $version->id) {
            return $cohort;
        }
        if ($cohort->stage_pipeline_version_id !== null) {
            throw new CohortStateException('A different stage pipeline version is already bound to this cohort.');
        }

        $cohort = DB::transaction(function () use ($cohort, $version): Cohort {
            $cohort->update(['stage_pipeline_version_id' => $version->id]);

            return $cohort->refresh();
        });

        $this->audit->record(AuditAction::CohortStagePipelineBound->value, 'cohort', $cohort->id, [], [
            'stage_pipeline_version_id' => $version->id,
        ]);

        return $cohort;
    }
}
