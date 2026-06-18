<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface AchievementPublisher
{
    /**
     * @param  array<string,mixed>  $achievement
     * @return array<string,mixed>
     */
    public function publish(string $accessToken, array $achievement): array;
}
