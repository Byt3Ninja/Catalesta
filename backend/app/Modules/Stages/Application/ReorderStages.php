<?php

declare(strict_types=1);

namespace App\Modules\Stages\Application;

use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderStages
{
    /**
     * Reorder the stages of a program by assigning order_index by position.
     *
     * @param  array<int, string>  $orderedStageIds  ULID strings in the desired order.
     *
     * @throws ValidationException if the provided ids don't exactly match the program's stages.
     */
    public function handle(Program $program, array $orderedStageIds): void
    {
        // Fetch all stage ids that actually belong to this program (tenant-scoped via global scope)
        $existingIds = $program->stages()
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->sort()
            ->values()
            ->toArray();

        $providedIds = collect($orderedStageIds)
            ->map(fn (mixed $id): string => (string) $id)
            ->sort()
            ->values()
            ->toArray();

        if ($existingIds !== $providedIds) {
            throw ValidationException::withMessages([
                'stage_ids' => ['The provided stage_ids must exactly match the stages belonging to this program.'],
            ]);
        }

        DB::transaction(function () use ($orderedStageIds): void {
            foreach ($orderedStageIds as $index => $stageId) {
                DB::table('program_stages')
                    ->where('id', $stageId)
                    ->update(['order_index' => $index]);
            }
        });
    }
}
