<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Exceptions;

use RuntimeException;

/**
 * A duplicate arrived while the first call is still running (claimed, no response
 * yet, lock not stale). Maps to HTTP 409 at the endpoint layer (Story 2.7).
 */
final class IdempotencyInFlightException extends RuntimeException
{
    public function __construct(string $scope, string $key)
    {
        parent::__construct("Idempotency key '{$key}' in scope '{$scope}' is already in flight.");
    }
}
