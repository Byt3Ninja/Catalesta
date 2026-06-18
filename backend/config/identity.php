<?php

declare(strict_types=1);

return [
    'provider' => env('IDENTITY_PROVIDER', 'mock'),
    'oidc' => [
        'issuer' => env('OIDC_ISSUER', 'http://startup-gate-mock:8080'),
        'client_id' => env('OIDC_CLIENT_ID', 'program-platform'),
        'client_secret' => env('OIDC_CLIENT_SECRET', 'local-secret'),
        'redirect_uri' => env('OIDC_REDIRECT_URI', 'http://localhost:3000/auth/callback'),
        'scopes' => [
            'openid', 'profile.basic.read', 'profile.professional.read',
            'profile.founder.read', 'profile.mentor.read',
            'profile.service_provider.read', 'profile.startups.read',
            'profile.documents.read',
        ],
    ],
    'profile_api_base_url' => env('PROFILE_API_BASE_URL', 'http://startup-gate-mock:8080/sg/api/v1'),
    // Mock signing keys (mock role only). In testing a fixed pair is injected.
    'mock' => [
        'private_key' => env('SG_MOCK_PRIVATE_KEY'),
        'public_key' => env('SG_MOCK_PUBLIC_KEY'),
        'kid' => env('SG_MOCK_KID', 'sg-mock-key-1'),
        'webhook_secret' => env('SG_MOCK_WEBHOOK_SECRET', 'mock-webhook-secret'),
    ],
];
