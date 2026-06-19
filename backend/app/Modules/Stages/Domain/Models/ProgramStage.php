<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProgramStage extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'key', 'name', 'type', 'order_index', 'parallel_group', 'current_published_version_id'];

    /**
     * @return array<string, string|class-string>
     */
    protected $casts = [
        'type' => StageType::class,
        'order_index' => 'integer',
    ];

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return HasMany<StageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(StageVersion::class);
    }
}
