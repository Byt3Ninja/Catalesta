<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Domain\Exceptions;

use RuntimeException;

/** Raised when a cohort is not in a valid state for the requested lifecycle transition (→ HTTP 409). */
final class CohortStateException extends RuntimeException {}
