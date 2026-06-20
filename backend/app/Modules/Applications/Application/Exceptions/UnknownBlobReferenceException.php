<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Exceptions;

use RuntimeException;

/**
 * A submission referenced a blob digest that does not exist in the store (not
 * finalized/verified). The submission is rejected — no snapshot may reference a
 * half-uploaded or unknown blob.
 */
final class UnknownBlobReferenceException extends RuntimeException
{
    public function __construct(string $digest)
    {
        parent::__construct("Submission references an unknown blob digest: {$digest}.");
    }
}
