<?php

declare(strict_types=1);

namespace App\Shared\Versioning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class VersionPublisher
{
    /**
     * Publish a versionable Eloquent model.
     *
     * Assigns the next sequential version_number within the same parent scope,
     * sets status to Published, and records published_at.
     *
     *
     * @throws VersionStateException if the record is not currently in Draft status.
     */
    public function publish(Model&Versionable $version): void
    {
        $currentStatus = $version->getAttribute('status');

        $currentValue = $currentStatus instanceof VersionStatus
            ? $currentStatus->value
            : (string) $currentStatus;

        if ($currentValue !== VersionStatus::Draft->value) {
            throw new VersionStateException(
                'Only Draft versions may be published.'
            );
        }

        $version->validateForPublish();

        $parentColumn = $version->versionParentColumn();
        $parentId = $version->getAttribute($parentColumn);
        $table = $version->getTable();

        DB::transaction(function () use ($version, $table, $parentColumn, $parentId): void {
            $maxVersionNumber = DB::table($table)
                ->where($parentColumn, $parentId)
                ->max('version_number') ?? 0;

            $version->setAttribute('version_number', (int) $maxVersionNumber + 1);
            $version->setAttribute('status', VersionStatus::Published);
            $version->setAttribute('published_at', now());
            $version->save();
        });
    }
}
