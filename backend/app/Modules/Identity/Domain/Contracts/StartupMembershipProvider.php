<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface StartupMembershipProvider
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function startups(string $accessToken): array;
}
