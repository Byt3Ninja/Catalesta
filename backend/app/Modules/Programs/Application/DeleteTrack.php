<?php

declare(strict_types=1);

namespace App\Modules\Programs\Application;

use App\Modules\Programs\Domain\Models\Track;
use Illuminate\Support\Facades\DB;

final class DeleteTrack
{
    /**
     * Delete a track within a transaction.
     *
     * NOTE: Tasks 4 and 5 will append cascade steps here:
     *   - Task 4 will add: DB deletion of program_stage_track pivot rows for this track.
     *   - Task 5 will add: DB nulling of cohort_participants.track_id for this track.
     * Those tables do not exist yet, so this method only deletes the track record itself.
     */
    public function handle(Track $track): void
    {
        DB::transaction(function () use ($track): void {
            $track->delete();
        });
    }
}
