<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

enum ProgramStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
    case Closed = 'closed';
}
