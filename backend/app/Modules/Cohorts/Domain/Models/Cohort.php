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

    protected $fillable = ['program_id', 'form_version_id', 'name', 'slug', 'status', 'enrollment_opens_at', 'enrollment_closes_at', 'starts_at', 'ends_at', 'capacity', 'timeline'];

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

    /**
     * True only while the cohort is open AND within its enrollment window. The
     * single source of truth for "can this cohort take an application right now?"
     * — used by the public apply view (1.4) and the submit guard (2.7). Pass the
     * comparison instant so the close-race re-check inside the submit transaction
     * evaluates against one consistent "now".
     */
    public function isAcceptingSubmissions(?\DateTimeInterface $now = null): bool
    {
        $now ??= now();

        return $this->status === CohortStatus::Open
            && ($this->enrollment_opens_at === null || $now >= $this->enrollment_opens_at)
            && ($this->enrollment_closes_at === null || $now <= $this->enrollment_closes_at);
    }
}
