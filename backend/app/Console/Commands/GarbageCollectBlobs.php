<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shared\Storage\Blob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Manual blob garbage collection (ADR-5: GC is deferred to a manual command,
 * ticketed debt — never automatic, never on-delete).
 *
 * Deletes ONLY blobs with refcount = 0. A blob with refcount > 0 is referenced
 * (e.g. by a submission snapshot) and is never touched. Dry-run by default.
 *
 * Ticketed debt: orphan accrual (refcount reaching 0 without prompt GC) is
 * accepted for P1a; scheduling/automation tracked separately. See Story 2.1.
 */
final class GarbageCollectBlobs extends Command
{
    protected $signature = 'blobs:gc {--apply : Actually delete orphans (default is a dry run)}';

    protected $description = 'Delete orphaned blobs (refcount = 0). Dry run unless --apply is passed.';

    public function handle(): int
    {
        $orphans = Blob::where('refcount', 0)->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphaned blobs (refcount = 0) found.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $this->info(($apply ? 'Deleting' : 'Would delete').' '.$orphans->count().' orphaned blob(s):');

        foreach ($orphans as $blob) {
            $this->line("  {$blob->digest} ({$blob->byte_size} bytes)");
            if ($apply) {
                Storage::disk($blob->disk)->delete($blob->path);
                $blob->delete();
            }
        }

        if (! $apply) {
            $this->comment('Dry run — re-run with --apply to delete.');
        }

        return self::SUCCESS;
    }
}
