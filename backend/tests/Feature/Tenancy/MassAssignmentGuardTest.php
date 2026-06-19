<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MassAssignmentGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_id_cannot_be_mass_assigned_and_is_forced_from_context(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $other = $this->createBareOrg('Other');

        $this->actingAsTenant($user, $org);

        $program = Program::query()->create([
            'name' => 'Legit',
            'organization_id' => $other->id, // spoof attempt
        ]);

        $this->assertSame($org->id, $program->fresh()->organization_id, 'org forced from context, spoof ignored');
    }
}
