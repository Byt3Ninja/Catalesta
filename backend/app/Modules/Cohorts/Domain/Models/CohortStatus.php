<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Domain\Models;

enum CohortStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Completed = 'completed';
}
