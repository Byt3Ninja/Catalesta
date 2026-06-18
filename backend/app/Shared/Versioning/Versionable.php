<?php

declare(strict_types=1);

namespace App\Shared\Versioning;

interface Versionable
{
    public function versionParentColumn(): string;

    public function validateForPublish(): void;
}
