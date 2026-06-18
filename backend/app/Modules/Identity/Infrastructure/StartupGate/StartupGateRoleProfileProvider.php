<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\StartupGate;

use App\Modules\Identity\Domain\Contracts\RoleProfileProvider;
use Illuminate\Support\Facades\Http;

final class StartupGateRoleProfileProvider implements RoleProfileProvider
{
    /**
     * {@inheritdoc}
     */
    public function roleProfiles(string $accessToken): array
    {
        $baseUrl = (string) config('identity.profile_api_base_url');

        $response = Http::withToken($accessToken)->get($baseUrl.'/me/role-profiles');

        return $response->json();
    }
}
