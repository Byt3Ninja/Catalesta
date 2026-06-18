<?php

declare(strict_types=1);

namespace Tests\Unit\Versioning;

use App\Shared\Versioning\ImmutableWhenPublished;
use App\Shared\Versioning\Versionable;
use App\Shared\Versioning\VersionPublisher;
use App\Shared\Versioning\VersionStateException;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @property string $id
 * @property string $parent_id
 * @property int $version_number
 * @property VersionStatus $status
 * @property Carbon|null $published_at
 */
final class FakeVersion extends Model implements Versionable
{
    use HasUlids, ImmutableWhenPublished;

    protected $table = 'fake_versions';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = ['status' => VersionStatus::class];

    public function versionParentColumn(): string
    {
        return 'parent_id';
    }

    public function validateForPublish(): void {}
}

final class VersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('fake_versions', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->ulid('parent_id')->index();
            $t->unsignedInteger('version_number')->default(0);
            $t->string('status')->default('draft');
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();
        });
    }

    public function test_publish_assigns_incrementing_version_numbers_per_parent(): void
    {
        $publisher = new VersionPublisher;
        $v1 = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        $v2 = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        $publisher->publish($v1);
        $publisher->publish($v2);
        $this->assertSame(1, $v1->fresh()->version_number);
        $this->assertSame(2, $v2->fresh()->version_number);
        $this->assertSame(VersionStatus::Published, $v1->fresh()->status);
    }

    public function test_published_version_cannot_be_edited(): void
    {
        $v = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        (new VersionPublisher)->publish($v);
        $this->expectException(VersionStateException::class);
        $v->refresh();
        $v->parent_id = 'p2';
        $v->save();
    }

    public function test_published_version_can_be_archived(): void
    {
        $v = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        (new VersionPublisher)->publish($v);
        $v->refresh();
        $v->status = VersionStatus::Archived;
        $v->save();
        $this->assertSame(VersionStatus::Archived, $v->fresh()->status);
    }

    public function test_archiving_with_another_dirty_column_still_throws(): void
    {
        $v = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        (new VersionPublisher)->publish($v);
        $v->refresh();
        $v->status = VersionStatus::Archived;
        $v->parent_id = 'p2';
        $this->expectException(VersionStateException::class);
        $v->save();
    }

    public function test_publish_rejects_already_published_version(): void
    {
        $v = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        $publisher = new VersionPublisher;
        $publisher->publish($v);
        $this->expectException(VersionStateException::class);
        $publisher->publish($v->fresh());
    }

    public function test_published_version_cannot_be_deleted(): void
    {
        $v = FakeVersion::create(['parent_id' => 'p1', 'status' => 'draft']);
        (new VersionPublisher)->publish($v);
        $v->refresh();
        $this->expectException(VersionStateException::class);
        $v->delete();
    }
}
