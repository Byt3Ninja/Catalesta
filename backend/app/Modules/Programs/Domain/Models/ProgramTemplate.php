<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-scoped program template.
 *
 * organization_id is stamped automatically by BelongsToTenant on creating.
 * id is excluded from fillable (HasUlids generates it automatically).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property array<string, mixed> $blueprint
 */
final class ProgramTemplate extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['name', 'slug', 'description', 'blueprint'];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'blueprint' => 'array',
    ];
}
