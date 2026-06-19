<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Named ProgramPolicyRecord (not ProgramPolicy) to avoid clashing with the
 * authorization ProgramPolicy class in App\Modules\Programs\Policies.
 */
final class ProgramPolicyRecord extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $table = 'program_policies';

    protected $fillable = ['program_id', 'key', 'value'];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'value' => 'array',
    ];

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
