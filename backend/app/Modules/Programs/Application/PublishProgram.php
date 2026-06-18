<?php

declare(strict_types=1);

namespace App\Modules\Programs\Application;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Shared\Audit\AuditLogger;

/**
 * Application service: transition a Program to Published status.
 *
 * Programs are NOT immutable after publish — they remain editable.
 * This service only handles the status transition and audit trail.
 */
final class PublishProgram
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Program $program): Program
    {
        $before = ['status' => $program->status->value];

        $program->status = ProgramStatus::Published;
        $program->save();

        $after = ['status' => $program->status->value];

        $this->audit->record(
            'program.published',
            'program',
            $program->id,
            $before,
            $after,
        );

        return $program;
    }
}
