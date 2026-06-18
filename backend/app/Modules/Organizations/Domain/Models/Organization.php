<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Organization extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'slug', 'branding'];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'branding' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function booting(): void
    {
        self::creating(function (self $organization): void {
            // Auto-derive slug from name if not provided
            if (! $organization->slug) {
                $organization->slug = Str::slug($organization->name);
            }
        });
    }
}
