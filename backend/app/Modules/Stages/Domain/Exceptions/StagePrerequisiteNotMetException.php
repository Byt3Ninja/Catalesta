<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Exceptions;

use RuntimeException;

final class StagePrerequisiteNotMetException extends RuntimeException
{
    public static function forStage(string $stageId): self
    {
        return new self("Stage [{$stageId}] has unmet prerequisites and cannot be entered.");
    }
}
