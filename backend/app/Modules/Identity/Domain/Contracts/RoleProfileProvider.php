<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface RoleProfileProvider
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function roleProfiles(string $accessToken): array;
}
