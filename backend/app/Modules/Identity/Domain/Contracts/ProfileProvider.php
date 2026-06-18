<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface ProfileProvider
{
    /**
     * @return array<string,mixed>
     */
    public function generalProfile(string $accessToken): array;
}
