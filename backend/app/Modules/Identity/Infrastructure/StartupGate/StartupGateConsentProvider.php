<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\StartupGate;

use App\Modules\Identity\Domain\Contracts\ConsentProvider;
use Illuminate\Support\Facades\Http;

final class StartupGateConsentProvider implements ConsentProvider
{
    /**
     * {@inheritdoc}
     */
    public function consents(string $accessToken): array
    {
        $baseUrl = (string) config('identity.profile_api_base_url');

        $response = Http::withToken($accessToken)->get($baseUrl.'/me/consents');

        $response->throw();

        return $response->json();
    }
}
