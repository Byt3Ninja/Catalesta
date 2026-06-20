<?php

declare(strict_types=1);

namespace App\Shared\Storage\Exceptions;

use RuntimeException;

final class BlobVerificationException extends RuntimeException
{
    public function __construct(string $digest)
    {
        parent::__construct("Stored blob failed sha256 verification for digest {$digest}.");
    }
}
