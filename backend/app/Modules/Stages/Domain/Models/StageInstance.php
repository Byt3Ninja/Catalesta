<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StageInstance extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['participant_stage_status_id', 'stage_version_id', 'started_at'];

    /**
     * @return array<string, string|class-string>
     */
    protected $casts = [
        'started_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ParticipantStageStatus, $this>
     */
    public function participantStageStatus(): BelongsTo
    {
        return $this->belongsTo(ParticipantStageStatus::class);
    }

    /**
     * @return BelongsTo<StageVersion, $this>
     */
    public function stageVersion(): BelongsTo
    {
        return $this->belongsTo(StageVersion::class);
    }
}
