<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class LinkedIdentityToken extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'scopes' => 'array',
        'expires_at' => 'datetime',
    ];
}
