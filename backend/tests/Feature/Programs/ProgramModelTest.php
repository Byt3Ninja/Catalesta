<?php

declare(strict_types=1);

namespace Tests\Feature\Programs;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationPermission;
use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Tenancy\TenantContext;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProgramModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_persists_with_ulid_slug_and_settings_cast(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $membership = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $org->id)
            ->where('external_user_id', $user->id)
            ->firstOrFail();

        $this->app->make(TenantContext::class)
            ->setOrganization($org->id, $membership, $membership->effectivePermissionKeys());

        $p = Program::create(['name' => 'Accelerator 2026', 'settings' => ['cohort_cap' => 20]]);
        $this->assertSame(26, strlen($p->id));
        $this->assertSame('accelerator-2026', $p->slug);
        $this->assertSame(['cohort_cap' => 20], $p->fresh()->settings);
        $this->assertSame($org->id, $p->organization_id); // auto-stamped by BelongsToTenant
    }

    public function test_catalog_seeds_phase2_permissions(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        foreach (['programs.manage', 'programs.publish', 'cohorts.manage', 'stages.manage'] as $key) {
            $this->assertTrue(OrganizationPermission::where('key', $key)->exists(), $key);
        }
    }
}
