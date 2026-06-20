<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use App\Shared\Storage\Exceptions\BlobTooLargeException;
use App\Shared\Storage\Exceptions\BlobVerificationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;

/**
 * Content-addressed file store over a Flysystem disk (MinIO 's3' by default).
 *
 * Identity is the sha256 of the content: storing the same bytes twice dedupes to
 * one object and bumps a refcount. A blob is only addressable once written and
 * sha256-verified (finalize-then-reference, AR-7). GC is manual (blobs:gc).
 */
final class ContentAddressedStore
{
    public function store(string $contents): Blob
    {
        $size = strlen($contents);
        $max = $this->maxBytes();
        if ($size > $max) {
            throw new BlobTooLargeException($size, $max);
        }

        $digest = hash('sha256', $contents);

        // Fast path: already stored → atomic refcount bump, no re-upload.
        $existing = Blob::find($digest);
        if ($existing !== null) {
            $this->incrementRef($digest);

            return $existing->refresh();
        }

        // Write, then verify the stored bytes before the row exists — a
        // half-written or corrupted object is never addressable.
        $path = $this->pathFor($digest);
        $disk = $this->disk();
        $disk->put($path, $contents);

        $stored = $disk->get($path);
        if ($stored === null || hash('sha256', $stored) !== $digest) {
            $disk->delete($path);
            throw new BlobVerificationException($digest);
        }

        try {
            return Blob::create([
                'digest' => $digest,
                'disk' => $this->diskName(),
                'path' => $path,
                'byte_size' => $size,
                'refcount' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            // ponytail: a concurrent first-writer won the digest PK race. The bytes
            // are identical (content-addressed), so adopt their row and bump refcount
            // instead of double-inserting. The PK is the cross-connection guard.
            $this->incrementRef($digest);

            return Blob::findOrFail($digest);
        }
    }

    public function retrieve(string $digest): ?string
    {
        $blob = Blob::find($digest);

        return $blob === null ? null : $this->disk($blob->disk)->get($blob->path);
    }

    public function exists(string $digest): bool
    {
        return Blob::whereKey($digest)->exists();
    }

    /** Atomic increment (UPDATE ... SET refcount = refcount + 1). */
    public function incrementRef(string $digest): void
    {
        Blob::where('digest', $digest)->increment('refcount');
    }

    /** Atomic decrement, floored at 0 (the > 0 guard prevents going negative). */
    public function decrementRef(string $digest): void
    {
        Blob::where('digest', $digest)->where('refcount', '>', 0)->decrement('refcount');
    }

    private function pathFor(string $digest): string
    {
        return sprintf('%s/%s/%s/%s', $this->prefix(), substr($digest, 0, 2), substr($digest, 2, 2), $digest);
    }

    private function disk(?string $name = null): Filesystem
    {
        return Storage::disk($name ?? $this->diskName());
    }

    private function diskName(): string
    {
        return (string) config('blob.disk');
    }

    private function maxBytes(): int
    {
        return (int) config('blob.max_bytes');
    }

    private function prefix(): string
    {
        return (string) config('blob.path_prefix');
    }
}
