<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\StartupGate;

use App\Modules\Identity\Domain\Contracts\AchievementPublisher;
use Illuminate\Support\Facades\Http;

final class StartupGateAchievementPublisher implements AchievementPublisher
{
    /**
     * {@inheritdoc}
     */
    public function publish(string $accessToken, array $achievement): array
    {
        $baseUrl = (string) config('identity.profile_api_base_url');

        $response = Http::withToken($accessToken)->post($baseUrl.'/program-achievements', $achievement);

        return $response->json();
    }
}
