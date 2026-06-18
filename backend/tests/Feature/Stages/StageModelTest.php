<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Tenancy\TenantContext;
use App\Shared\Versioning\VersionPublisher;
use App\Shared\Versioning\VersionStateException;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_stage_persists_with_ulid_type_enum_and_organization_id(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $membership = OrganizationMembership::query()
            ->where('organization_id', $org->id)
            ->where('external_user_id', $user->id)
            ->firstOrFail();

        $this->app->make(TenantContext::class)
            ->setOrganization($org->id, $membership, $membership->effectivePermissionKeys());

        $program = Program::create(['name' => 'Accelerator 2026']);

        $stage = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'screening',
            'name' => 'Screening Stage',
            'type' => StageType::Screening,
            'order_index' => 1,
        ]);

        // ULID id is 26 chars
        $this->assertSame(26, strlen($stage->id));

        // type cast to enum
        $this->assertInstanceOf(StageType::class, $stage->type);
        $this->assertSame(StageType::Screening, $stage->type);

        // order_index is int
        $this->assertSame(1, $stage->order_index);

        // organization_id auto-stamped by BelongsToTenant
        $this->assertSame($org->id, $stage->organization_id);

        // persisted value round-trips
        $fresh = $stage->fresh();
        $this->assertSame(StageType::Screening, $fresh->type);
        $this->assertSame(1, $fresh->order_index);
    }

    public function test_stage_version_publishes_with_version_number_one_and_becomes_immutable(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $membership = OrganizationMembership::query()
            ->where('organization_id', $org->id)
            ->where('external_user_id', $user->id)
            ->firstOrFail();

        $this->app->make(TenantContext::class)
            ->setOrganization($org->id, $membership, $membership->effectivePermissionKeys());

        $program = Program::create(['name' => 'Accelerator 2026']);

        $stage = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'interview',
            'name' => 'Interview Stage',
            'type' => StageType::Interview,
            'order_index' => 2,
        ]);

        $version = StageVersion::create([
            'program_stage_id' => $stage->id,
            'config' => ['duration_minutes' => 30],
        ]);

        // Draft by default
        $this->assertSame(VersionStatus::Draft, $version->status);
        $this->assertSame(0, $version->version_number);

        // Publish via VersionPublisher
        $publisher = $this->app->make(VersionPublisher::class);
        $publisher->publish($version);

        $version->refresh();
        $this->assertSame(VersionStatus::Published, $version->status);
        $this->assertSame(1, $version->version_number);
        $this->assertNotNull($version->published_at);

        // config cast to array
        $this->assertSame(['duration_minutes' => 30], $version->config);

        // organization_id auto-stamped
        $this->assertSame($org->id, $version->organization_id);

        // Immutability: editing a published version throws VersionStateException
        $version->config = ['duration_minutes' => 60];
        $this->expectException(VersionStateException::class);
        $version->save();
    }

    public function test_second_stage_version_publishes_as_version_number_two(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $membership = OrganizationMembership::query()
            ->where('organization_id', $org->id)
            ->where('external_user_id', $user->id)
            ->firstOrFail();

        $this->app->make(TenantContext::class)
            ->setOrganization($org->id, $membership, $membership->effectivePermissionKeys());

        $program = Program::create(['name' => 'Accelerator 2026']);

        $stage = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'demo',
            'name' => 'Demo Day',
            'type' => StageType::Demo,
            'order_index' => 3,
        ]);

        $publisher = $this->app->make(VersionPublisher::class);

        $v1 = StageVersion::create(['program_stage_id' => $stage->id, 'config' => []]);
        $publisher->publish($v1);

        $v2 = StageVersion::create(['program_stage_id' => $stage->id, 'config' => ['notes' => 'v2']]);
        $publisher->publish($v2);

        $v2->refresh();
        $this->assertSame(2, $v2->version_number);
        $this->assertSame(VersionStatus::Published, $v2->status);
    }
}
