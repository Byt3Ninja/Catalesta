<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProgramRoleRequirement extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $table = 'program_role_requirements';

    protected $fillable = ['program_id', 'role_key', 'min_count', 'max_count', 'is_required'];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_required' => 'boolean',
        'min_count' => 'integer',
        'max_count' => 'integer',
    ];

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
