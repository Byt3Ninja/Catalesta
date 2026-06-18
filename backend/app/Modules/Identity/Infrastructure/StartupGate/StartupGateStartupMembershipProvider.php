<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\StartupGate;

use App\Modules\Identity\Domain\Contracts\StartupMembershipProvider;
use Illuminate\Support\Facades\Http;

final class StartupGateStartupMembershipProvider implements StartupMembershipProvider
{
    /**
     * {@inheritdoc}
     */
    public function startups(string $accessToken): array
    {
        $baseUrl = (string) config('identity.profile_api_base_url');

        $response = Http::withToken($accessToken)->get($baseUrl.'/me/startups');

        return $response->json();
    }
}
