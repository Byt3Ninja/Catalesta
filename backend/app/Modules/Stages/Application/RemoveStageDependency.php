<?php

declare(strict_types=1);

namespace App\Modules\Stages\Application;

use App\Modules\Stages\Domain\Models\StageDependency;

final class RemoveStageDependency
{
    public function handle(StageDependency $dependency): void
    {
        $dependency->delete();
    }
}
