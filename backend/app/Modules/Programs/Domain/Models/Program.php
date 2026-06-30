<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Shared\Tenancy\BelongsToTenant;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Program extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = ['name', 'slug', 'status', 'type', 'description', 'settings', 'template_id'];

    protected static function newFactory(): ProgramFactory
    {
        return ProgramFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'status' => ProgramStatus::class,
        'type' => ProgramType::class,
    ];

    protected static function booting(): void
    {
        self::creating(function (self $program): void {
            if (! $program->slug) {
                $program->slug = Str::slug($program->name);
            }
        });
    }

    /**
     * @return HasMany<ProgramStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(ProgramStage::class);
    }

    /**
     * @return HasMany<ProgramPolicyRecord, $this>
     */
    public function policies(): HasMany
    {
        return $this->hasMany(ProgramPolicyRecord::class);
    }

    /**
     * @return HasMany<ProgramRoleRequirement, $this>
     */
    public function roleRequirements(): HasMany
    {
        return $this->hasMany(ProgramRoleRequirement::class);
    }

    /**
     * @return HasMany<StageTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(StageTransition::class);
    }

    /**
     * Published, immutable snapshots of this program (FR-010/012).
     *
     * @return HasMany<ProgramVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class);
    }
}
