<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * A frozen application submission. The submission_snapshot is write-once — once
 * recorded it is never altered, so what an applicant submitted cannot be silently
 * changed (FR-031). organization_id is server-set by BelongsToTenant, never
 * mass-assignable.
 */
final class ApplicationSubmission extends Model
{
    use BelongsToTenant;
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['cohort_id', 'submission_snapshot'];

    protected $casts = [
        'submission_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Write-once: a persisted submission can never be updated (AC-3).
        self::updating(function (): void {
            throw new RuntimeException('application_submissions are immutable (write-once snapshot).');
        });
    }
}
