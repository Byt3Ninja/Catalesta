<?php

declare(strict_types=1);

namespace App\Shared\Storage\Exceptions;

use RuntimeException;

final class BlobTooLargeException extends RuntimeException
{
    public function __construct(int $size, int $max)
    {
        parent::__construct("Blob of {$size} bytes exceeds the {$max}-byte limit.");
    }
}
