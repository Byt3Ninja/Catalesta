<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Shared\Storage\ContentAddressedStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BlobGarbageCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        config(['blob.disk' => 's3', 'blob.max_bytes' => 1024, 'blob.path_prefix' => 'blobs']);
    }

    public function test_gc_dry_run_deletes_nothing(): void
    {
        $store = app(ContentAddressedStore::class);
        $blob = $store->store('orphan');
        $store->decrementRef($blob->digest); // refcount → 0

        $this->artisan('blobs:gc')->assertExitCode(0);

        $this->assertDatabaseCount('blobs', 1); // still there — dry run
    }

    public function test_gc_apply_deletes_only_refcount_zero_blobs(): void
    {
        $store = app(ContentAddressedStore::class);

        $orphan = $store->store('orphan');
        $store->decrementRef($orphan->digest); // refcount 0 → collectable

        $referenced = $store->store('referenced'); // refcount 1 → must survive

        $this->artisan('blobs:gc --apply')->assertExitCode(0);

        $this->assertFalse($store->exists($orphan->digest), 'orphan blob should be collected');
        $this->assertNull(Storage::disk('s3')->get($orphan->path));

        $this->assertTrue($store->exists($referenced->digest), 'referenced blob must NEVER be collected');
        $this->assertSame('referenced', $store->retrieve($referenced->digest));
    }

    public function test_gc_never_touches_a_referenced_blob_even_with_apply(): void
    {
        $store = app(ContentAddressedStore::class);
        $blob = $store->store('keep me'); // refcount 1

        $this->artisan('blobs:gc --apply')->assertExitCode(0);

        $this->assertTrue($store->exists($blob->digest));
    }

    /**
     * Records the deliberate design decision (AR-5): blobs are content-addressed
     * and global — NOT tenant-owned. The absence of organization_id is intentional,
     * not an oversight. Tenant isolation lives on the reference (Story 2.6).
     */
    public function test_blobs_table_is_intentionally_not_tenant_scoped(): void
    {
        $this->assertFalse(
            Schema::hasColumn('blobs', 'organization_id'),
            'blobs must NOT carry organization_id — content addressing is global by digest (AR-5 dedup).'
        );
    }
}
