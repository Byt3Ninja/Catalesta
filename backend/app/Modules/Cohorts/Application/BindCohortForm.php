<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Application;

use App\Modules\Cohorts\Domain\Exceptions\CohortStateException;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Binds a published form version to a draft cohort. Idempotent when the same
 * version is re-bound; refuses (409) a different version or a non-draft cohort.
 */
final class BindCohortForm
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws CohortStateException on a non-draft cohort or a conflicting bound version
     * @throws ModelNotFoundException when no published version with that id exists in the tenant
     */
    public function handle(Cohort $cohort, string $formVersionId): Cohort
    {
        if ($cohort->status !== CohortStatus::Draft) {
            throw new CohortStateException('A form can only be bound while the cohort is a draft.');
        }

        // Tenant-scoped (BelongsToTenant) + published-only — a foreign or draft version is not bindable.
        $version = FormVersion::query()->where('status', 'published')->findOrFail($formVersionId);

        if ($cohort->form_version_id === $version->id) {
            return $cohort; // idempotent re-bind of the same version
        }

        if ($cohort->form_version_id !== null) {
            throw new CohortStateException('A different form version is already bound to this cohort.');
        }

        $cohort = DB::transaction(function () use ($cohort, $version): Cohort {
            $cohort->update(['form_version_id' => $version->id]);

            return $cohort->refresh();
        });

        $this->audit->record(AuditAction::CohortFormBound->value, 'cohort', $cohort->id, [], [
            'form_version_id' => $version->id,
        ]);

        return $cohort;
    }
}
