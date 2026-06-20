<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Shared\Storage\Blob;
use App\Shared\Storage\ContentAddressedStore;
use App\Shared\Storage\Exceptions\BlobTooLargeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ContentAddressedStoreTest extends TestCase
{
    use RefreshDatabase;

    private ContentAddressedStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        config(['blob.disk' => 's3', 'blob.max_bytes' => 1024, 'blob.path_prefix' => 'blobs']);
        $this->store = app(ContentAddressedStore::class);
    }

    public function test_key_is_sha256_of_content_and_blob_is_retrievable(): void
    {
        $blob = $this->store->store('hello world');

        $this->assertSame(hash('sha256', 'hello world'), $blob->digest);
        $this->assertSame(11, $blob->byte_size);
        $this->assertSame(1, $blob->refcount);
        $this->assertSame('hello world', $this->store->retrieve($blob->digest));
        $this->assertTrue($this->store->exists($blob->digest));
    }

    public function test_storing_identical_content_twice_dedupes_to_one_blob_with_refcount_two(): void
    {
        $a = $this->store->store('same bytes');
        $b = $this->store->store('same bytes');

        $this->assertSame($a->digest, $b->digest);
        $this->assertDatabaseCount('blobs', 1);
        $this->assertSame(2, Blob::find($a->digest)->refcount);
    }

    public function test_stored_blob_is_immutable_redundant_store_does_not_rewrite_bytes(): void
    {
        $blob = $this->store->store('immutable');
        $path = $blob->path;

        // Storing again must not create a second object or change bytes at the key.
        $this->store->store('immutable');

        $this->assertSame('immutable', Storage::disk('s3')->get($path));
        $this->assertCount(1, Storage::disk('s3')->allFiles());
    }

    public function test_oversize_content_is_rejected_fail_closed_with_no_blob_written(): void
    {
        $this->expectException(BlobTooLargeException::class);
        try {
            $this->store->store(str_repeat('x', 1025)); // max_bytes = 1024
        } finally {
            $this->assertDatabaseCount('blobs', 0);
            $this->assertCount(0, Storage::disk('s3')->allFiles());
        }
    }

    public function test_empty_content_has_a_deterministic_digest(): void
    {
        $blob = $this->store->store('');

        $this->assertSame(hash('sha256', ''), $blob->digest);
        $this->assertSame(0, $blob->byte_size);
        $this->assertSame('', $this->store->retrieve($blob->digest));
    }

    public function test_increment_and_decrement_refcount_is_atomic_and_floors_at_zero(): void
    {
        $blob = $this->store->store('refcounted'); // refcount 1
        $digest = $blob->digest;

        $this->store->incrementRef($digest);
        $this->assertSame(2, Blob::find($digest)->refcount);

        $this->store->decrementRef($digest);
        $this->store->decrementRef($digest);
        $this->assertSame(0, Blob::find($digest)->refcount);

        // Floors at 0 — never goes negative.
        $this->store->decrementRef($digest);
        $this->assertSame(0, Blob::find($digest)->refcount);
    }

    public function test_retrieve_returns_null_for_unknown_digest(): void
    {
        $this->assertNull($this->store->retrieve(hash('sha256', 'never stored')));
        $this->assertFalse($this->store->exists(hash('sha256', 'never stored')));
    }
}
