<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

use App\Modules\Identity\Domain\Exceptions\InvalidTokenException;

interface IdentityProvider
{
    /**
     * @param  array<int,string>  $scopes
     */
    public function buildAuthorizationUrl(string $state, string $nonce, string $codeChallenge, array $scopes): string;

    /**
     * @return array{id_token:string, access_token:string, refresh_token:?string, expires_in:int}
     */
    public function exchangeCode(string $code, string $codeVerifier): array;

    /**
     * @return array<string,mixed> validated ID-token claims; throws InvalidTokenException on any failure
     *
     * @throws InvalidTokenException
     */
    public function validateIdToken(string $idToken, string $expectedNonce): array;

    /**
     * @return array{access_token:string, refresh_token:?string, expires_in:int}
     */
    public function refresh(string $refreshToken): array;

    public function revoke(string $token): void;

    /**
     * @return array<string,mixed>
     */
    public function userinfo(string $accessToken): array;
}
