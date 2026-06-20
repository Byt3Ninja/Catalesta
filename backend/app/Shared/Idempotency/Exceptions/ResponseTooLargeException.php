<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Exceptions;

use RuntimeException;

/**
 * The operation's response exceeded the configured idempotency response cap. The
 * claim is released and the work is NOT recorded — never truncate-and-replay.
 */
final class ResponseTooLargeException extends RuntimeException
{
    public function __construct(string $scope, string $key)
    {
        parent::__construct("Response for idempotency key '{$key}' in scope '{$scope}' exceeds the configured cap.");
    }
}
