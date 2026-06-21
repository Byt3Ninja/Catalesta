<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A Learning Telemetry event (FR-080). Tenant-owned and append-only (DB triggers
 * reject UPDATE/DELETE). `BelongsToTenant` keeps reads tenant-scoped and satisfies
 * the tenant-isolation arch test (NFR-001). Public events (viewed/started) carry
 * NO actor identity — only org + cohort + event + timestamp — so telemetry never
 * becomes a PII store. Written best-effort via LearningTelemetry, never inline.
 */
final class LearningEvent extends Model
{
    use BelongsToTenant;
    use HasUlids;

    // Append-only: occurred_at is the event time; created_at is DB-defaulted. There
    // is no updated_at (the row is never updated).
    public $timestamps = false;

    protected $fillable = ['cohort_id', 'event_name', 'payload', 'occurred_at'];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
