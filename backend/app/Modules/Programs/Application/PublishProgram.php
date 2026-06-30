<?php

declare(strict_types=1);

namespace App\Modules\Programs\Application;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Programs\Domain\Models\ProgramVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Entitlement\EntitlementService;
use App\Shared\Versioning\VersionPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Application service: publish a Program (FR-010/012).
 *
 * Gated through EntitlementService (FR-060, allow-all in P1a) at the call site,
 * mirroring OpenCohort. In one transaction it flips the program to Published,
 * freezes an immutable ProgramVersion (a snapshot of the publishable config), and
 * audits program.published (FR-052). The Program row itself stays editable —
 * editing and re-publishing creates a new version, never mutating a prior one.
 */
final class PublishProgram
{
    public function __construct(
        private readonly EntitlementService $entitlement,
        private readonly VersionPublisher $versionPublisher,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Program $program): Program
    {
        $this->entitlement->check('program.publish');

        return DB::transaction(function () use ($program): Program {
            $before = ['status' => $program->status->value];

            $program->status = ProgramStatus::Published;
            $program->save();

            // Freeze an immutable snapshot of the publishable config. The row is
            // inserted once by VersionPublisher (a single INSERT with the sealed
            // version_number + Published status) — no transient draft row.
            $version = new ProgramVersion([
                'program_id' => $program->id,
                'definition' => $this->snapshot($program),
            ]);
            $version->organization_id = $program->organization_id;

            // Assigns the next version_number within this program and seals it.
            $this->versionPublisher->publish($version);

            $after = ['status' => $program->status->value];

            $this->audit->record(
                AuditAction::ProgramPublished->value,
                'program',
                $program->id,
                $before,
                $after,
            );

            return $program;
        });
    }

    /**
     * The publishable program config captured into the immutable version.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Program $program): array
    {
        return [
            'name' => $program->name,
            'type' => $program->type?->value,
            'description' => $program->description,
            'settings' => $program->settings,
        ];
    }
}
