<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Reporting\Application\LearningTelemetry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Story 2.8 (FR-080) — the Learning Telemetry substrate. Events are append-only,
 * tenant-stamped from an explicit org (public emit points have no TenantContext),
 * and carry no actor identity.
 */
final class LearningEventTest extends TestCase
{
    use RefreshDatabase;

    private function recorder(): LearningTelemetry
    {
        return app(LearningTelemetry::class);
    }

    public function test_recorder_stamps_the_explicit_org_with_no_tenant_context(): void
    {
        $org = $this->createBareOrg('Org A');
        $cohortId = (string) Str::ulid();

        $event = $this->recorder()->record('application.viewed', $cohortId, $org->id, ['k' => 'v']);

        $this->assertNotNull($event);
        $this->assertSame($org->id, $event->organization_id);
        $this->assertSame('application.viewed', $event->event_name);
        $this->assertDatabaseHas('learning_events', [
            'organization_id' => $org->id,
            'cohort_id' => $cohortId,
            'event_name' => 'application.viewed',
        ]);
    }

    public function test_events_cannot_be_updated(): void
    {
        $org = $this->createBareOrg('Org A');
        $event = $this->recorder()->record('application.started', (string) Str::ulid(), $org->id);

        $this->expectException(QueryException::class);
        DB::table('learning_events')->where('id', $event?->id)->update(['event_name' => 'tampered']);
    }

    public function test_events_cannot_be_deleted(): void
    {
        $org = $this->createBareOrg('Org A');
        $event = $this->recorder()->record('application.started', (string) Str::ulid(), $org->id);

        $this->expectException(QueryException::class);
        DB::table('learning_events')->where('id', $event?->id)->delete();
    }
}
