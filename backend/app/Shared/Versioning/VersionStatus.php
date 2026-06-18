<?php

declare(strict_types=1);

namespace App\Shared\Versioning;

enum VersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
