<?php

declare(strict_types=1);

namespace App\Shared\Idempotency\Exceptions;

use RuntimeException;

/**
 * Same (scope, key) reused with a different request fingerprint. Maps to HTTP 422
 * at the endpoint layer (Story 2.7). Never a wrong cached replay.
 */
final class IdempotencyConflictException extends RuntimeException
{
    public function __construct(string $scope, string $key)
    {
        parent::__construct("Idempotency key '{$key}' in scope '{$scope}' was reused with a different request.");
    }
}
