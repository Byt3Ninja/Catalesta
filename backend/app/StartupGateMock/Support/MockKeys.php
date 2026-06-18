<?php

declare(strict_types=1);

namespace App\StartupGateMock\Support;

use RuntimeException;

/**
 * Manages the RSA keypair for the Startup Gate mock OIDC provider.
 *
 * Key resolution priority:
 *   1. config('identity.mock.private_key') / public_key — PEM or base64-encoded PEM
 *   2. Per-process generated pair, cached in static properties (sufficient for tests)
 *
 * The static cache guarantees that jwks() and token-signing share the SAME keypair
 * within a single process/test run.
 */
final class MockKeys
{
    private static ?string $cachedPrivatePem = null;

    private static ?string $cachedPublicPem = null;

    /**
     * Returns the RSA private key as a PEM string.
     */
    public static function privateKeyPem(): string
    {
        self::ensureLoaded();

        return (string) self::$cachedPrivatePem;
    }

    /**
     * Returns the RSA public key as a PEM string.
     */
    public static function publicKeyPem(): string
    {
        self::ensureLoaded();

        return (string) self::$cachedPublicPem;
    }

    /**
     * Returns the key ID (kid).
     */
    public static function kid(): string
    {
        return (string) config('identity.mock.kid', 'sg-mock-key-1');
    }

    /**
     * Builds a JWKS array from the public key.
     *
     * @return array{keys: array<int, array{kty: string, alg: string, use: string, kid: string, n: string, e: string}>}
     */
    public static function jwks(): array
    {
        self::ensureLoaded();

        $pubKey = openssl_pkey_get_public(self::$cachedPublicPem);

        if ($pubKey === false) {
            throw new RuntimeException('MockKeys: failed to load public key for JWKS export.');
        }

        $details = openssl_pkey_get_details($pubKey);

        if ($details === false || ! isset($details['rsa'])) {
            throw new RuntimeException('MockKeys: public key is not RSA.');
        }

        return [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => self::kid(),
                    'n' => self::base64url($details['rsa']['n']),
                    'e' => self::base64url($details['rsa']['e']),
                ],
            ],
        ];
    }

    /**
     * Resets cached keypair — intended for test teardown only.
     */
    public static function reset(): void
    {
        self::$cachedPrivatePem = null;
        self::$cachedPublicPem = null;
    }

    // -------------------------------------------------------------------------

    private static function ensureLoaded(): void
    {
        if (self::$cachedPrivatePem !== null) {
            return;
        }

        $configPrivate = config('identity.mock.private_key');
        $configPublic = config('identity.mock.public_key');

        if ($configPrivate && $configPublic) {
            // Support base64-encoded PEMs (e.g., from env vars set via sg-mock:keys)
            self::$cachedPrivatePem = self::decodePem((string) $configPrivate);
            self::$cachedPublicPem = self::decodePem((string) $configPublic);

            return;
        }

        // Generate a fresh RSA-2048 pair for this process (local dev / testing)
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            throw new RuntimeException('MockKeys: openssl_pkey_new() failed — ensure OpenSSL is available.');
        }

        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        $publicPem = $details['key'] ?? null;

        if (! $privatePem || ! $publicPem) {
            throw new RuntimeException('MockKeys: failed to export generated keypair.');
        }

        self::$cachedPrivatePem = $privatePem;
        self::$cachedPublicPem = $publicPem;
    }

    /**
     * Decodes a value that may be a plain PEM or a base64-encoded PEM.
     */
    private static function decodePem(string $value): string
    {
        $trimmed = trim($value);

        if (str_starts_with($trimmed, '-----')) {
            return $trimmed;
        }

        // Attempt base64 decode
        $decoded = base64_decode($trimmed, strict: true);

        if ($decoded !== false && str_starts_with(trim($decoded), '-----')) {
            return trim($decoded);
        }

        // Fall back to original value
        return $trimmed;
    }

    /**
     * Base64url-encodes a binary string (RFC 7517).
     */
    private static function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
