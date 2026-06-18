<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

enum StageRuleType: string
{
    case Entry = 'entry';
    case Exit = 'exit';
}
