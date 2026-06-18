<?php

declare(strict_types=1);

namespace App\Modules\Stages\Application;

use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionPublisher;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Support\Facades\DB;

final class PublishStageVersion
{
    public function __construct(
        private VersionPublisher $publisher,
        private AuditLogger $audit,
    ) {}

    /**
     * Publish a stage version and update the parent stage's current_published_version_id.
     *
     * Wrapped in a transaction: both the version publish and the pointer update succeed or
     * both roll back.
     *
     * @throws VersionStateException if the version is not in Draft status.
     */
    public function handle(StageVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            $this->publisher->publish($version);

            $version->programStage()->update([
                'current_published_version_id' => $version->id,
            ]);
        });

        $this->audit->record(
            'stage.published',
            'stage_version',
            $version->id,
            [],
            ['status' => 'published', 'version_number' => $version->version_number],
        );
    }
}
