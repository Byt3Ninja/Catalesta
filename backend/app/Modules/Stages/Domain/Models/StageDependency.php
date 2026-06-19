<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class StageDependency extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_stage_id', 'depends_on_program_stage_id'];
}
