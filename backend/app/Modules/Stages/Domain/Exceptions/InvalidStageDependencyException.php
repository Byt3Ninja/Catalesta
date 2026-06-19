<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Exceptions;

final class InvalidStageDependencyException extends \RuntimeException
{
    public static function selfDependency(): self
    {
        return new self('A stage cannot depend on itself.');
    }

    public static function crossProgram(): self
    {
        return new self('The prerequisite stage must belong to the same program.');
    }

    public static function cycle(): self
    {
        return new self('Adding this dependency would create a cycle in the stage graph.');
    }
}
