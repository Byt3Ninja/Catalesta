<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Http\Resources\CohortResource;
use App\Shared\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CohortFoundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_action_has_cohort_form_bound(): void
    {
        $this->assertSame('cohort.form_bound', AuditAction::CohortFormBound->value);
    }

    public function test_resource_exposes_bound_form_version_id(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = Cohort::create(['program_id' => (string) Str::ulid(), 'name' => 'Spring', 'status' => 'draft']);
        $cohort->update(['form_version_id' => 'fv_123']);

        $out = (new CohortResource($cohort->refresh()))->toArray(Request::create('/'));

        $this->assertSame('fv_123', $out['bound_form_version_id']);
    }

    public function test_open_and_bindform_require_cohorts_manage(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = Cohort::create(['program_id' => (string) Str::ulid(), 'name' => 'Spring', 'status' => 'draft']);

        // owner role has cohorts.manage
        $this->assertTrue(Gate::forUser($user)->allows('open', $cohort));
        $this->assertTrue(Gate::forUser($user)->allows('bindForm', $cohort));
    }
}
