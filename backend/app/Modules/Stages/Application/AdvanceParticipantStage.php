<?php

declare(strict_types=1);

namespace App\Modules\Stages\Application;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Stages\Domain\Exceptions\StageNotPublishedException;
use App\Modules\Stages\Domain\Exceptions\StagePrerequisiteNotMetException;
use App\Modules\Stages\Domain\Models\ParticipantStageState;
use App\Modules\Stages\Domain\Models\ParticipantStageStatus;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageDependency;
use App\Modules\Stages\Domain\Models\StageInstance;
use App\Modules\Stages\Domain\Models\StageRuleType;
use App\Shared\Rules\ExpressionEvaluator;
use Illuminate\Support\Facades\DB;

final class AdvanceParticipantStage
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator,
    ) {}

    /**
     * Attempt to enter a participant into a stage.
     *
     * - Requires the stage to have a current published version.
     * - Evaluates the entry rule (if any) against the resolved context.
     * - If the entry rule passes (or there is none): sets status=InProgress, creates a StageInstance
     *   bound to the published version active at entry time (immutable invariant).
     * - If the entry rule fails: sets/keeps status=Blocked, no instance created.
     *
     * @param  array<string, mixed>  $context  Additional evaluation context (merged over base context).
     *
     * @throws StageNotPublishedException if the stage has no published version.
     */
    public function enter(
        Cohort $cohort,
        Account $participant,
        ProgramStage $stage,
        array $context = [],
    ): ParticipantStageStatus {
        $unmet = StageDependency::query()
            ->where('program_stage_id', $stage->id)
            ->pluck('depends_on_program_stage_id');

        if ($unmet->isNotEmpty()) {
            $completed = ParticipantStageStatus::query()
                ->where('cohort_id', $cohort->id)
                ->where('account_id', $participant->id)
                ->whereIn('program_stage_id', $unmet)
                ->where('status', ParticipantStageState::Completed->value)
                ->pluck('program_stage_id');

            if ($completed->count() < $unmet->unique()->count()) {
                throw StagePrerequisiteNotMetException::forStage($stage->id);
            }
        }

        if ($stage->current_published_version_id === null) {
            throw StageNotPublishedException::forStage($stage->id);
        }

        $publishedVersionId = $stage->current_published_version_id;

        // Build evaluation context
        $evalContext = $this->buildContext($cohort, $context);

        // Load the entry rule for the published version (if any)
        $entryRule = $stage
            ->versions()
            ->where('id', $publishedVersionId)
            ->first()
            ?->stageRules()
            ->where('type', StageRuleType::Entry->value)
            ->first();

        $allowed = $entryRule === null
            || $this->evaluator->evaluate($entryRule->expression ?? [], $evalContext);

        return DB::transaction(function () use ($cohort, $participant, $stage, $publishedVersionId, $allowed): ParticipantStageStatus {
            /** @var ParticipantStageStatus $status */
            $status = ParticipantStageStatus::updateOrCreate(
                [
                    'cohort_id' => $cohort->id,
                    'account_id' => $participant->id,
                    'program_stage_id' => $stage->id,
                ],
                $allowed
                    ? ['status' => ParticipantStageState::InProgress->value, 'entered_at' => now()]
                    : ['status' => ParticipantStageState::Blocked->value],
            );

            if ($allowed) {
                StageInstance::create([
                    'participant_stage_status_id' => $status->id,
                    'stage_version_id' => $publishedVersionId,
                    'started_at' => now(),
                ]);
            }

            return $status->fresh();
        });
    }

    /**
     * Attempt to complete a stage for a participant.
     *
     * - Requires the participant status to be InProgress.
     * - Evaluates the exit rule (if any) for the stage version the instance is bound to.
     * - If exit rule passes (or none): sets status=Completed + completed_at.
     * - If exit rule fails: keeps status=InProgress.
     *
     * @param  array<string, mixed>  $context  Additional evaluation context.
     */
    public function complete(
        ParticipantStageStatus $status,
        array $context = [],
    ): ParticipantStageStatus {
        // Guard: only InProgress participants may complete
        if ($status->status !== ParticipantStageState::InProgress) {
            return $status;
        }

        // Load the stage and most-recent instance to get the bound version
        $stage = $status->programStage;
        $cohort = $status->cohort;

        /** @var StageInstance|null $latestInstance */
        $latestInstance = $status->stageInstances()->latest()->first();

        $exitRule = null;

        if ($latestInstance !== null) {
            $exitRule = $latestInstance
                ->stageVersion
                ?->stageRules()
                ->where('type', StageRuleType::Exit->value)
                ->first();
        } elseif ($stage !== null && $stage->current_published_version_id !== null) {
            $exitRule = $stage
                ->versions()
                ->where('id', $stage->current_published_version_id)
                ->first()
                ?->stageRules()
                ->where('type', StageRuleType::Exit->value)
                ->first();
        }

        // For exit rule evaluation, merge caller-supplied context with any cohort defaults
        $evalContext = $cohort !== null
            ? $this->buildContext($cohort, $context)
            : $context;

        $passes = $exitRule === null
            || $this->evaluator->evaluate($exitRule->expression ?? [], $evalContext);

        if (! $passes) {
            return $status;
        }

        return DB::transaction(function () use ($status): ParticipantStageStatus {
            $status->update([
                'status' => ParticipantStageState::Completed->value,
                'completed_at' => now(),
            ]);

            return $status->fresh();
        });
    }

    /**
     * Build the base evaluation context from cohort and participant data,
     * merged with any caller-supplied context overrides.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function buildContext(Cohort $cohort, array $extra): array
    {
        $base = [
            'cohort.is_open' => $cohort->enrollment_opens_at !== null
                && $cohort->enrollment_opens_at->isPast()
                && ($cohort->enrollment_closes_at === null || $cohort->enrollment_closes_at->isFuture()),
        ];

        return array_merge($base, $extra);
    }
}
