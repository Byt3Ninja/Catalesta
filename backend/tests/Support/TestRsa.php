<?php

declare(strict_types=1);

namespace Tests\Support;

final class TestRsa
{
    /**
     * Generate an RSA-2048 keypair and return a JWKS document suitable for use in tests.
     *
     * @return array{private: string, public: string, jwks: array{keys: list<array<string,string>>}}
     */
    public static function generate(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new \RuntimeException('Failed to generate RSA key: '.openssl_error_string());
        }

        openssl_pkey_export($resource, $privatePem);

        $details = openssl_pkey_get_details($resource);

        if ($details === false || ! isset($details['rsa'])) {
            throw new \RuntimeException('Failed to get RSA key details');
        }

        $n = self::base64url($details['rsa']['n']);
        $e = self::base64url($details['rsa']['e']);

        $publicPem = $details['key'];

        return [
            'private' => $privatePem,
            'public' => $publicPem,
            'jwks' => [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'alg' => 'RS256',
                        'use' => 'sig',
                        'kid' => 'sg-mock-key-1',
                        'n' => $n,
                        'e' => $e,
                    ],
                ],
            ],
        ];
    }

    private static function base64url(string $binary): string
    {
        return strtr(rtrim(base64_encode($binary), '='), '+/', '-_');
    }
}
