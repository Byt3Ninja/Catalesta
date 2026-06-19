<?php

declare(strict_types=1);

namespace App\Modules\Stages\Application;

use App\Modules\Stages\Domain\Exceptions\InvalidStageDependencyException;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageDependency;

final class AddStageDependency
{
    public function handle(ProgramStage $stage, string $dependsOnStageId): StageDependency
    {
        if ($stage->id === $dependsOnStageId) {
            throw InvalidStageDependencyException::selfDependency();
        }

        // Prerequisite must exist in the SAME program (tenant-scoped find via global scope)
        $prereq = ProgramStage::query()->find($dependsOnStageId);

        if ($prereq === null || $prereq->program_id !== $stage->program_id) {
            throw InvalidStageDependencyException::crossProgram();
        }

        // Cycle check: would adding stage→prereq create a path prereq →* stage?
        if ($this->reaches($dependsOnStageId, $stage->id)) {
            throw InvalidStageDependencyException::cycle();
        }

        return StageDependency::query()->firstOrCreate([
            'program_stage_id' => $stage->id,
            'depends_on_program_stage_id' => $dependsOnStageId,
        ]);
    }

    /**
     * Does $from reach $target by following depends_on edges?
     *
     * Performs iterative DFS over the existing dependency graph.
     */
    private function reaches(string $from, string $target): bool
    {
        $seen = [];
        $stack = [$from];

        while ($stack !== []) {
            $node = array_pop($stack);

            if ($node === $target) {
                return true;
            }

            if (isset($seen[$node])) {
                continue;
            }

            $seen[$node] = true;

            $edges = StageDependency::query()
                ->where('program_stage_id', $node)
                ->pluck('depends_on_program_stage_id');

            foreach ($edges as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }
}
