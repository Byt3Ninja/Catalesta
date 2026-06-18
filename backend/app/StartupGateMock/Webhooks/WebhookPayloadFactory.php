<?php

declare(strict_types=1);

namespace App\StartupGateMock\Webhooks;

use Illuminate\Support\Str;

final class WebhookPayloadFactory
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function profileUpdated(array $data): array
    {
        return $this->build('ProfileUpdated', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function consentRevoked(array $data): array
    {
        return $this->build('ConsentRevoked', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function roleProfileApproved(array $data): array
    {
        return $this->build('RoleProfileApproved', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function achievementPublished(array $data): array
    {
        return $this->build('AchievementPublished', $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function build(string $type, array $data): array
    {
        return [
            'id' => (string) Str::ulid(),
            'type' => $type,
            'version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ];
    }
}
