<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Application;

use App\Modules\Cohorts\Domain\Exceptions\CohortStateException;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Entitlement\EntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * Opens a draft cohort for applications. The form is bound beforehand (BindCohortForm)
 * and the enrollment window is set via PATCH /cohorts/{id}; this transition only
 * validates state, gates on EntitlementService (FR-060), flips status to Open, and
 * audits (cohort.opened). The window is optional — a null window opens with no time bound.
 */
final class OpenCohort
{
    public function __construct(
        private readonly EntitlementService $entitlement,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws CohortStateException if the cohort is not a draft or has no bound form
     */
    public function handle(Cohort $cohort): Cohort
    {
        if ($cohort->status !== CohortStatus::Draft) {
            throw new CohortStateException('Only a draft cohort can be opened.');
        }

        if ($cohort->form_version_id === null) {
            throw new CohortStateException('A form must be bound before the cohort can be opened.');
        }

        $this->entitlement->check('cohort.open');

        $cohort = DB::transaction(function () use ($cohort): Cohort {
            $cohort->update(['status' => CohortStatus::Open]);

            return $cohort->refresh();
        });

        $this->audit->record(AuditAction::CohortOpened->value, 'cohort', $cohort->id, [], [
            'form_version_id' => $cohort->form_version_id,
        ]);

        return $cohort;
    }
}
