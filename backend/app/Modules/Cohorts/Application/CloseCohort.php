<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Application;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Closes an open cohort manually (status → Closed) and audits cohort.closed.
 * Once closed the public URL no longer accepts submissions (422 in Story 2.7).
 */
final class CloseCohort
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Cohort $cohort): Cohort
    {
        $cohort = DB::transaction(function () use ($cohort): Cohort {
            $cohort->update(['status' => CohortStatus::Closed]);

            return $cohort->refresh();
        });

        $this->audit->record(AuditAction::CohortClosed->value, 'cohort', $cohort->id, [], []);

        return $cohort;
    }
}
