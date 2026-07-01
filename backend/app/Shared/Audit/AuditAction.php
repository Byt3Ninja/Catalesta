<?php

declare(strict_types=1);

namespace App\Shared\Audit;

/**
 * The enumerated P1a audited action set (FR-052). This is the canonical registry:
 * every action here is recorded immutably via AuditLogger when its operation runs.
 * Each emitting operation lives in its own story (program/cohort in Epic 1;
 * application.submitted in 2.7; submission.scored in 3.1; decisions in 3.2/3.3)
 * and references these values. A change to this set is caught by the completeness
 * test — it must stay in lockstep with FR-052.
 */
enum AuditAction: string
{
    case ProgramPublished = 'program.published';
    case CohortOpened = 'cohort.opened';
    case CohortFormBound = 'cohort.form_bound';
    case CohortClosed = 'cohort.closed';
    case ApplicationSubmitted = 'application.submitted';
    case SubmissionScored = 'submission.scored';
    case DecisionRecorded = 'decision.recorded';
    case DecisionReopened = 'decision.reopened';
    case DecisionsExported = 'decisions.exported';
    case StagePipelinePublished = 'stage_pipeline.published';
    case CohortStagePipelineBound = 'cohort.stage_pipeline_bound';
    case ScoringModelPublished = 'scoring_model.published';
}
