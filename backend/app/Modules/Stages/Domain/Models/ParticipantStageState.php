<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

enum ParticipantStageState: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Blocked = 'blocked';
}
