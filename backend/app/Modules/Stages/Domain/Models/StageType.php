<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

enum StageType: string
{
    case Application = 'application';
    case Screening = 'screening';
    case Interview = 'interview';
    case Mentorship = 'mentorship';
    case Training = 'training';
    case Assignment = 'assignment';
    case Review = 'review';
    case Evaluation = 'evaluation';
    case Demo = 'demo';
    case Graduation = 'graduation';
    case Custom = 'custom';
}
