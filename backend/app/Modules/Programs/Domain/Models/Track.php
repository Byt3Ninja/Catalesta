<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class Track extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'key', 'name', 'description', 'order_index'];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'order_index' => 'integer',
    ];
}
