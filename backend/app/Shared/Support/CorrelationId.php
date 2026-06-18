<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Str;

final class CorrelationId
{
    private static ?string $value = null;

    public static function set(string $id): void
    {
        self::$value = $id;
    }

    public static function get(): string
    {
        return self::$value ??= 'corr_'.Str::ulid();
    }

    public static function reset(): void
    {
        self::$value = null;
    }
}
