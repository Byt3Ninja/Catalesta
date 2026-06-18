<?php

declare(strict_types=1);

namespace App\Shared\Versioning;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait ImmutableWhenPublished
 *
 * Registers Eloquent model guards that prevent updates and deletes once a
 * versioned record reaches Published status.
 *
 * Narrow exception: an update whose ONLY dirty attribute is `status` AND
 * whose new value is `archived` (published → archived transition) is allowed.
 */
trait ImmutableWhenPublished
{
    protected static function bootImmutableWhenPublished(): void
    {
        static::updating(function (Model $model): void {
            $original = $model->getOriginal('status');

            // Normalise to backing value — status may be cast to VersionStatus enum.
            $originalValue = $original instanceof VersionStatus
                ? $original->value
                : (string) $original;

            if ($originalValue !== VersionStatus::Published->value) {
                return;
            }

            // Currently published — only permit status→archived with no other dirty columns.
            $dirty = $model->getDirty();

            if (array_keys($dirty) === ['status']) {
                $newStatus = $dirty['status'];

                // getDirty() may return the enum instance or its string value.
                $newValue = $newStatus instanceof VersionStatus
                    ? $newStatus->value
                    : (string) $newStatus;

                if ($newValue === VersionStatus::Archived->value) {
                    return;
                }
            }

            throw new VersionStateException(
                'A published version record cannot be modified.'
            );
        });

        static::deleting(function (Model $model): void {
            $original = $model->getOriginal('status');

            $originalValue = $original instanceof VersionStatus
                ? $original->value
                : (string) $original;

            if ($originalValue === VersionStatus::Published->value) {
                throw new VersionStateException(
                    'A published version record cannot be deleted.'
                );
            }
        });
    }
}
