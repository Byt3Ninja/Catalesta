<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

/**
 * Composes the idempotency fingerprint. The actor MUST be part of it so the same
 * key from a different actor never replays another's response (AC-7). Callers
 * route the actor (e.g. Startup Gate `sub`) and the request shape through here.
 */
final class RequestFingerprint
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function for(string $actor, array $payload): string
    {
        return hash('sha256', $actor.'|'.self::canonicalJson($payload));
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function canonicalJson(array $payload): string
    {
        self::ksortRecursive($payload);

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function ksortRecursive(array &$payload): void
    {
        ksort($payload);
        foreach ($payload as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}
