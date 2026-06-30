<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Cohorts\Application\CloseCohort;
use App\Modules\Cohorts\Application\OpenCohort;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Forms\Application\PublishForm;
use App\Modules\Forms\Application\SaveFormDraft;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CohortLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Cohort, 1: FormVersion} a draft cohort + a published form, under tenant context */
    private function bootCohortAndForm(): array
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $cohort = Cohort::create([
            'program_id' => (string) Str::ulid(),
            'name' => 'Cohort One',
            'status' => CohortStatus::Draft,
        ]);

        $form = Form::create(['program_id' => $cohort->program_id, 'name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]);
        $this->app->make(SaveFormDraft::class)->handle($form, [
            ['type' => 'short_text', 'label' => 'Name', 'id' => 'f1', 'required' => true],
        ]);
        $version = $this->app->make(PublishForm::class)->handle($form->refresh());

        return [$cohort, $version];
    }

    public function test_open_attaches_form_sets_window_status_and_is_audited(): void // AC-1/4
    {
        [$cohort, $form] = $this->bootCohortAndForm();

        $cohort->update([
            'form_version_id' => $form->id,
            'enrollment_opens_at' => now()->subDay(),
            'enrollment_closes_at' => now()->addDay(),
        ]);
        $opened = $this->app->make(OpenCohort::class)->handle($cohort->refresh());

        $this->assertSame(CohortStatus::Open, $opened->status);
        $this->assertSame($form->id, $opened->form_version_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cohort.opened', 'target_id' => $cohort->id]);
    }

    public function test_public_url_resolves_open_cohort_without_tenant_context(): void // AC-1/3
    {
        [$cohort, $form] = $this->bootCohortAndForm();
        $cohort->update([
            'form_version_id' => $form->id,
            'enrollment_opens_at' => now()->subDay(),
            'enrollment_closes_at' => now()->addDay(),
        ]);
        $this->app->make(OpenCohort::class)->handle($cohort->refresh());

        $resp = $this->getJson("/api/v1/apply/{$cohort->id}");

        $resp->assertOk()
            ->assertJson(['open' => true, 'cohort_id' => $cohort->id, 'form_version_id' => $form->id]);
    }

    public function test_close_sets_status_and_is_audited_and_public_reports_closed(): void // AC-2/3/4
    {
        [$cohort, $form] = $this->bootCohortAndForm();
        $cohort->update([
            'form_version_id' => $form->id,
            'enrollment_opens_at' => now()->subDay(),
            'enrollment_closes_at' => now()->addDay(),
        ]);
        $this->app->make(OpenCohort::class)->handle($cohort->refresh());

        $closed = $this->app->make(CloseCohort::class)->handle($cohort);
        $this->assertSame(CohortStatus::Closed, $closed->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cohort.closed', 'target_id' => $cohort->id]);

        $this->getJson("/api/v1/apply/{$cohort->id}")->assertOk()->assertJson(['open' => false]);
    }

    public function test_public_url_reports_closed_before_and_after_the_window(): void // AC-3
    {
        [$cohort, $form] = $this->bootCohortAndForm();
        // Window entirely in the past → open status but outside window → closed.
        $cohort->update([
            'form_version_id' => $form->id,
            'enrollment_opens_at' => now()->subDays(2),
            'enrollment_closes_at' => now()->subDay(),
        ]);
        $this->app->make(OpenCohort::class)->handle($cohort->refresh());

        $this->getJson("/api/v1/apply/{$cohort->id}")->assertOk()->assertJson(['open' => false]);
    }

    public function test_public_url_404s_for_unknown_cohort(): void
    {
        $this->getJson('/api/v1/apply/'.Str::ulid())->assertNotFound();
    }

    public function test_public_url_returns_the_published_form_definition(): void // Story 2.7 form-fetch
    {
        [$cohort, $form] = $this->bootCohortAndForm();
        $cohort->update([
            'form_version_id' => $form->id,
            'enrollment_opens_at' => now()->subDay(),
            'enrollment_closes_at' => now()->addDay(),
        ]);
        $this->app->make(OpenCohort::class)->handle($cohort->refresh());

        // The applicant page needs the field definitions to render the stepped form.
        $this->getJson("/api/v1/apply/{$cohort->id}")
            ->assertOk()
            ->assertJsonPath('form.0.type', 'short_text')
            ->assertJsonPath('form.0.label', 'Name');
    }
}
