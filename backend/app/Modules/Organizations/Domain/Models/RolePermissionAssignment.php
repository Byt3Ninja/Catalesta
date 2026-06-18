<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for role_permission_assignments.
 * Uses HasUlids to auto-generate the ULID primary key on insert.
 */
final class RolePermissionAssignment extends Pivot
{
    use HasUlids;

    public $incrementing = false;

    protected $table = 'role_permission_assignments';
}
