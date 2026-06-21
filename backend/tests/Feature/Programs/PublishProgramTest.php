<?php

declare(strict_types=1);

namespace Tests\Feature\Programs;

use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Programs\Application\CloneProgram;
use App\Modules\Programs\Application\PublishProgram;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Programs\Domain\Models\ProgramVersion;
use App\Shared\Entitlement\EntitlementService;
use App\Shared\Versioning\VersionStateException;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Story 1.2 — publish creates an immutable program version (AC-1), gated through
 * EntitlementService (AC-2), audited (AC-4), tenant-isolated (AC-5 / AR-6).
 */
final class PublishProgramTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Program, 1: Organization} a draft program under tenant context */
    private function bootDraftProgram(string $orgName = 'Acme', string $name = 'Original'): array
    {
        [$user, $org] = $this->bootUserWithOrg($orgName);
        $this->actingAsTenant($user, $org);

        $program = Program::create(['name' => $name, 'status' => ProgramStatus::Draft]);

        return [$program, $org];
    }

    public function test_publish_creates_a_published_immutable_version(): void // AC-1
    {
        [$program] = $this->bootDraftProgram();

        $published = $this->app->make(PublishProgram::class)->handle($program);

        $this->assertSame(ProgramStatus::Published, $published->status);

        $version = ProgramVersion::query()->where('program_id', $program->id)->firstOrFail();
        $this->assertSame(VersionStatus::Published, $version->status);
        $this->assertSame(1, $version->version_number);
        $this->assertNotNull($version->published_at);
        $this->assertSame('Original', $version->definition['name']);
    }

    public function test_published_version_cannot_be_updated(): void // AC-1
    {
        [$program] = $this->bootDraftProgram();
        $this->app->make(PublishProgram::class)->handle($program);

        $version = ProgramVersion::query()->where('program_id', $program->id)->firstOrFail();

        $this->expectException(VersionStateException::class);
        $version->definition = ['name' => 'tampered'];
        $version->save();
    }

    public function test_published_version_cannot_be_deleted(): void // AC-1
    {
        [$program] = $this->bootDraftProgram();
        $this->app->make(PublishProgram::class)->handle($program);

        $version = ProgramVersion::query()->where('program_id', $program->id)->firstOrFail();

        $this->expectException(VersionStateException::class);
        $version->delete();
    }

    public function test_republish_creates_a_new_version_without_altering_the_prior_one(): void // AC-1
    {
        [$program] = $this->bootDraftProgram(name: 'Original');

        $service = $this->app->make(PublishProgram::class);
        $service->handle($program);

        // Edit the program (the row stays editable) and re-publish.
        $program->name = 'Edited';
        $program->save();
        $service->handle($program->refresh());

        $versions = ProgramVersion::query()
            ->where('program_id', $program->id)
            ->orderBy('version_number')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertSame(1, $versions[0]->version_number);
        $this->assertSame(2, $versions[1]->version_number);
        // The first published version is untouched; the second carries the edit.
        $this->assertSame('Original', $versions[0]->definition['name']);
        $this->assertSame('Edited', $versions[1]->definition['name']);
    }

    public function test_publish_writes_program_published_audit(): void // AC-4
    {
        [$program, $org] = $this->bootDraftProgram();

        $this->app->make(PublishProgram::class)->handle($program);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'program.published',
            'target_id' => $program->id,
            'organization_id' => $org->id,
        ]);
    }

    public function test_entitlement_gate_blocks_publish_and_nothing_is_written(): void // AC-2
    {
        // Bind a throwing entitlement service — the gate sits at the call site,
        // before the transaction, so a block must leave NO version/audit/status change.
        $this->app->bind(EntitlementService::class, fn () => new class implements EntitlementService
        {
            public function check(string $action): void
            {
                throw new \RuntimeException('blocked: '.$action);
            }
        });

        [$program] = $this->bootDraftProgram();

        try {
            $this->app->make(PublishProgram::class)->handle($program);
            $this->fail('Expected the entitlement gate to block publish.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('program.publish', $e->getMessage());
        }

        $this->assertSame(ProgramStatus::Draft, $program->refresh()->status);
        $this->assertSame(0, ProgramVersion::query()->where('program_id', $program->id)->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'program.published', 'target_id' => $program->id]);
    }

    public function test_cross_tenant_publish_is_blocked_via_api(): void // AC-5
    {
        // Org B owns a draft program.
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Publish');
        $createResponse = $this->actingAs($ownerB, 'web')
            ->withHeader('X-Organization-Id', $orgB->id)
            ->postJson('/api/v1/programs', ['name' => 'Org B Program'])
            ->assertStatus(201);
        $programBId = $createResponse->json('data.id');

        // Org A user tries to publish Org B's program with their own valid header.
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Publish');
        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$programBId}/publish");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('programs', ['id' => $programBId, 'status' => 'draft']);
        $this->assertDatabaseMissing('program_versions', ['program_id' => $programBId]);
    }

    public function test_cloning_a_published_program_yields_a_fresh_draft_leaving_the_version_untouched(): void // AC-3 × AC-1
    {
        [$program] = $this->bootDraftProgram(name: 'Published Original');
        $this->app->make(PublishProgram::class)->handle($program);

        $clone = $this->app->make(CloneProgram::class)->handle($program->refresh(), 'Clone Of Original');

        // The clone is a brand-new draft with no published versions of its own.
        $this->assertSame(ProgramStatus::Draft, $clone->status);
        $this->assertNotSame($program->id, $clone->id);
        $this->assertSame(0, ProgramVersion::query()->where('program_id', $clone->id)->count());

        // The source's published version is untouched.
        $this->assertSame(1, ProgramVersion::query()->where('program_id', $program->id)->count());
    }

    public function test_program_versions_are_tenant_scoped(): void // AR-6
    {
        // Publish a program under Org A → creates a program_versions row.
        [$programA, $orgA] = $this->bootDraftProgram('Org A Ver');
        $this->app->make(PublishProgram::class)->handle($programA);

        $this->assertGreaterThan(
            0,
            ProgramVersion::withoutGlobalScope('tenant')->where('program_id', $programA->id)->count(),
        );

        // Under Org B's tenant context, Org A's versions must be invisible.
        [$userB, $orgB] = $this->bootUserWithOrg('Org B Ver');
        $this->actingAsTenant($userB, $orgB);

        $visibleOrgIds = ProgramVersion::all()->pluck('organization_id')->unique()->toArray();
        $this->assertNotContains($orgA->id, $visibleOrgIds, 'program_versions leaked across tenants');
    }
}
