<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface ConsentProvider
{
    /**
     * @return array<int,array{scope:string,granted:bool,reference:string}>
     */
    public function consents(string $accessToken): array;
}
