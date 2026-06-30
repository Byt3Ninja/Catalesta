<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

enum ProgramType: string
{
    case Accelerator = 'accelerator';
    case Incubator = 'incubator';
    case Hackathon = 'hackathon';
    case Fellowship = 'fellowship';
}
