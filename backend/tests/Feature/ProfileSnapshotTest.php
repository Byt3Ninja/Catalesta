<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Application\CaptureProfileSnapshot;
use App\Modules\Identity\Domain\Models\LinkedIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfileSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_writes_immutable_snapshot_with_hash(): void
    {
        $u = LinkedIdentity::projectFromClaims(['sub' => 'sg_user_01', 'email' => 'a@b.c'])->account;
        $snap = app(CaptureProfileSnapshot::class)->capture($u, 'identity', null, ['biography' => 'hi'], 'profile.basic.read');
        $this->assertSame(64, strlen($snap->hash));
        $this->assertSame(['biography' => 'hi'], $snap->payload_json);
    }

    public function test_snapshot_cannot_be_updated(): void
    {
        $u = LinkedIdentity::projectFromClaims(['sub' => 'sg_user_01'])->account;
        $snap = app(CaptureProfileSnapshot::class)->capture($u, 'identity', null, ['x' => 1], 'profile.basic.read');
        $this->expectException(\RuntimeException::class);
        $snap->payload_json = ['x' => 2];
        $snap->save();
    }
}
