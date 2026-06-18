<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Exceptions;

use RuntimeException;

final class StageNotPublishedException extends RuntimeException
{
    public static function forStage(string $stageId): self
    {
        return new self("Stage [{$stageId}] has no published version and cannot be entered.");
    }
}
