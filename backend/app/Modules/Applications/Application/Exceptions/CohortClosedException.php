<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Exceptions;

use RuntimeException;

/**
 * A submission arrived for a cohort that is not accepting applications (closed,
 * unpublished, or outside its enrollment window). Re-checked INSIDE the submit
 * transaction so a close that races a submit still wins (FR-033, ★ 2.7). Maps to
 * HTTP 422 at the endpoint layer (bootstrap/app.php renderer).
 */
final class CohortClosedException extends RuntimeException
{
    public function __construct(string $cohortId)
    {
        parent::__construct("Cohort '{$cohortId}' is not accepting applications.");
    }
}
