<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Exceptions;

use RuntimeException;

/**
 * The submission answer payload exceeded the configured maximum. Fail-closed —
 * never store a truncated snapshot.
 */
final class SubmissionTooLargeException extends RuntimeException
{
    public function __construct(int $size, int $max)
    {
        parent::__construct("Submission payload of {$size} bytes exceeds the {$max}-byte limit.");
    }
}
