<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Program extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'status' => ProgramStatus::class,
    ];

    protected static function booting(): void
    {
        self::creating(function (self $program): void {
            if (! $program->slug) {
                $program->slug = Str::slug($program->name);
            }
        });
    }
}
