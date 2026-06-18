<?php

declare(strict_types=1);

namespace Tests\Contract;

use Tests\TestCase;

final class OidcDiscoveryContractTest extends TestCase
{
    public function test_discovery_document_shape(): void
    {
        $this->getJson('/.well-known/openid-configuration')
            ->assertOk()
            ->assertJsonStructure(['issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri', 'response_types_supported', 'subject_types_supported', 'id_token_signing_alg_values_supported']);
    }

    public function test_jwks_exposes_rs256_signing_key(): void
    {
        $this->getJson('/.well-known/jwks.json')
            ->assertOk()
            ->assertJsonPath('keys.0.kty', 'RSA')
            ->assertJsonPath('keys.0.alg', 'RS256')
            ->assertJsonPath('keys.0.use', 'sig');
    }
}
