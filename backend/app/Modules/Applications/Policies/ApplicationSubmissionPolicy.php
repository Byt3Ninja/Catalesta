<?php

declare(strict_types=1);

namespace App\Modules\Applications\Policies;

use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Identity\Domain\Models\ExternalUser;

/**
 * Authorization for reading application submissions (Story 2.8, FR-034).
 *
 * viewAny/view: any authenticated member of the resolved tenant may read their
 * org's submissions. BelongsToTenant's global scope + the ResolveTenant
 * middleware do the isolation, so no extra permission key is required — this
 * mirrors CohortPolicy. Submissions are write-once and never mutated, so there
 * are no create/update/delete abilities here.
 */
final class ApplicationSubmissionPolicy
{
    public function viewAny(ExternalUser $user): bool
    {
        return true;
    }

    public function view(ExternalUser $user, ApplicationSubmission $submission): bool
    {
        return true;
    }
}
