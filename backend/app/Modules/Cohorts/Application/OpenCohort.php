<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Application;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Entitlement\EntitlementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Opens a cohort for applications: attaches the published form, sets the
 * enrollment window, and flips status to Open. Gated through EntitlementService
 * (FR-060) and audited (cohort.opened).
 */
final class OpenCohort
{
    public function __construct(
        private readonly EntitlementService $entitlement,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Cohort $cohort, FormVersion $form, Carbon $opensAt, Carbon $closesAt): Cohort
    {
        $this->entitlement->check('cohort.open');

        $cohort = DB::transaction(function () use ($cohort, $form, $opensAt, $closesAt): Cohort {
            $cohort->update([
                'form_version_id' => $form->id,
                'enrollment_opens_at' => $opensAt,
                'enrollment_closes_at' => $closesAt,
                'status' => CohortStatus::Open,
            ]);

            return $cohort->refresh();
        });

        $this->audit->record(AuditAction::CohortOpened->value, 'cohort', $cohort->id, [], [
            'form_version_id' => $form->id,
        ]);

        return $cohort;
    }
}
