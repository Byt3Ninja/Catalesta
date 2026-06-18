<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AuditLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
}
