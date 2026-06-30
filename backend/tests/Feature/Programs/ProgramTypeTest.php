<?php

declare(strict_types=1);

namespace Tests\Feature\Programs;

use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Programs\Domain\Models\ProgramType;
use App\Modules\Programs\Domain\Models\ProgramVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProgramTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_accepts_type_and_resource_emits_it(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenantRequest($user, $org);

        $response = $this->postJson('/api/v1/programs', [
            'name' => 'Spring Accelerator',
            'type' => 'accelerator',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'accelerator');

        $this->assertSame(ProgramType::Accelerator, Program::firstOrFail()->type);
    }

    public function test_create_without_type_is_null(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenantRequest($user, $org);

        $this->postJson('/api/v1/programs', ['name' => 'No Type'])
            ->assertStatus(201)
            ->assertJsonPath('data.type', null);
    }

    public function test_invalid_type_is_422(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenantRequest($user, $org);

        $this->postJson('/api/v1/programs', ['name' => 'Bad', 'type' => 'bootcamp'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.type.0', fn ($v) => str_contains((string) $v, 'type'));
    }

    public function test_update_sets_and_clears_type(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'P', 'status' => ProgramStatus::Draft]);

        $this->actingAsTenantRequest($user, $org);

        $this->patchJson("/api/v1/programs/{$program->id}", ['type' => 'incubator'])
            ->assertStatus(200)->assertJsonPath('data.type', 'incubator');

        $this->patchJson("/api/v1/programs/{$program->id}", ['type' => null])
            ->assertStatus(200)->assertJsonPath('data.type', null);
    }

    public function test_clone_carries_type(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Src', 'status' => ProgramStatus::Draft, 'type' => ProgramType::Fellowship]);

        $this->actingAsTenantRequest($user, $org);

        $this->postJson("/api/v1/programs/{$program->id}/clone", ['name' => 'Copy'])
            ->assertStatus(201)->assertJsonPath('data.type', 'fellowship');
    }

    public function test_publish_snapshot_captures_type(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::create(['name' => 'Pub', 'status' => ProgramStatus::Draft, 'type' => ProgramType::Hackathon]);

        $this->actingAsTenantRequest($user, $org);

        $this->postJson("/api/v1/programs/{$program->id}/publish")->assertStatus(200);

        $version = ProgramVersion::where('program_id', $program->id)->firstOrFail();
        $this->assertSame('hackathon', $version->definition['type']);
    }

    public function test_cross_tenant_program_is_404(): void
    {
        [$userA, $orgA] = $this->bootUserWithOrg('OrgAlpha');
        $this->actingAsTenant($userA, $orgA);
        $program = Program::create(['name' => 'A', 'status' => ProgramStatus::Draft]);

        [$userB, $orgB] = $this->bootUserWithOrg('OrgBeta');
        $this->actingAsTenantRequest($userB, $orgB);

        $this->patchJson("/api/v1/programs/{$program->id}", ['type' => 'incubator'])
            ->assertStatus(404);
    }
}
