<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ParticipantStageStatus extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['cohort_id', 'external_user_id', 'program_stage_id', 'status', 'entered_at', 'completed_at'];

    /**
     * @return array<string, string|class-string>
     */
    protected $casts = [
        'status' => ParticipantStageState::class,
        'entered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Cohort, $this>
     */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /**
     * @return BelongsTo<ProgramStage, $this>
     */
    public function programStage(): BelongsTo
    {
        return $this->belongsTo(ProgramStage::class);
    }

    /**
     * @return HasMany<StageInstance, $this>
     */
    public function stageInstances(): HasMany
    {
        return $this->hasMany(StageInstance::class);
    }
}
