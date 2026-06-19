<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Cohort extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'name', 'slug', 'status', 'enrollment_opens_at', 'enrollment_closes_at', 'starts_at', 'ends_at', 'capacity', 'timeline'];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'status' => CohortStatus::class,
        'enrollment_opens_at' => 'datetime',
        'enrollment_closes_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'capacity' => 'int',
        'timeline' => 'array',
    ];

    protected static function booting(): void
    {
        self::creating(function (self $cohort): void {
            if (! $cohort->slug) {
                $cohort->slug = Str::slug($cohort->name);
            }
        });
    }
}
