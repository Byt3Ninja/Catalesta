<?php

declare(strict_types=1);

namespace App\Shared\Idempotency;

enum IdempotencyStatus: string
{
    case Claimed = 'claimed';
    case Completed = 'completed';
}
