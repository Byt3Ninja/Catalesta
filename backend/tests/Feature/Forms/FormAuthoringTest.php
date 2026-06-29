<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FormAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_returns_201_with_an_empty_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $res = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/forms', ['name' => 'Intake']);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Intake')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.latest_version', 0)
            ->assertJsonPath('data.published_version_ids', []);

        $formId = $res->json('data.id');
        $this->assertNotNull($res->json('data.current_draft_version_id'));
        $this->assertDatabaseHas('form_versions', ['form_id' => $formId, 'status' => 'draft', 'version_number' => 0]);
    }

    public function test_create_form_requires_a_name(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/forms', ['name' => ''])
            ->assertStatus(422);
    }

    public function test_create_form_requires_authentication(): void
    {
        $this->postJson('/api/v1/forms', ['name' => 'X'])->assertStatus(401);
    }

    public function test_index_lists_only_the_callers_org_forms(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        Form::create(['name' => 'Mine']);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');
        $this->actingAsTenant($other, $otherOrg);
        Form::create(['name' => 'Theirs']);

        // Reset TenantContext so ResolveTenant middleware can re-resolve it from the header.
        // BelongsToTenant checks has() before isSystem(), so a pre-set context would
        // cause runAsSystem() inside the middleware to filter by the wrong org.
        $this->resetTenantContext();

        $res = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/forms');
        $res->assertStatus(200);
        $names = array_column($res->json('data'), 'name');
        $this->assertSame(['Mine'], $names);
    }

    public function test_show_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Mine']);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        // Reset TenantContext so the middleware resolves it fresh from the header.
        $this->resetTenantContext();

        $this->actingAs($other, 'web')
            ->withHeader('X-Organization-Id', $otherOrg->id)
            ->getJson("/api/v1/forms/{$form->id}")
            ->assertStatus(404);
    }

    public function test_member_without_forms_manage_cannot_create_form(): void
    {
        [, $org] = $this->bootUserWithOrg();

        $member = $this->makeAccount();
        $memberMembership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        $this->resetTenantContext();

        $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/forms', ['name' => 'Forbidden Form'])
            ->assertStatus(403);
    }

    public function test_versions_index_lists_in_version_order(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'status' => 'published', 'version_number' => 1, 'content_hash' => str_repeat('a', 64), 'definition' => [], 'published_at' => now()]);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]); // draft

        $res = $this->actingAsTenantRequest($user, $org)->getJson("/api/v1/forms/{$form->id}/versions");
        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data'));
        $this->assertSame([1, 0], array_column($res->json('data'), 'version'));
    }

    public function test_version_show_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        $v = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->getJson("/api/v1/form-versions/{$v->id}")
            ->assertStatus(404);
    }

    public function test_save_draft_replaces_definition(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $fields = [['type' => 'short_text', 'label' => 'Name', 'id' => 'f1', 'required' => true]];
        $res = $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/forms/{$form->id}/draft", ['fields' => $fields]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.fields.0.label', 'Name');
    }

    public function test_save_draft_rejects_a_forbidden_code_key_with_422(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/forms/{$form->id}/draft", ['fields' => [
                ['type' => 'number', 'label' => 'Score', 'expr' => 'evil()'],
            ]])
            ->assertStatus(422);
    }

    public function test_save_draft_returns_409_when_there_is_no_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        // a fully published form with no working draft
        FormVersion::create(['form_id' => $form->id, 'status' => 'published', 'version_number' => 1, 'content_hash' => str_repeat('a', 64), 'definition' => [], 'published_at' => now()]);

        $this->actingAsTenantRequest($user, $org)
            ->patchJson("/api/v1/forms/{$form->id}/draft", ['fields' => [['type' => 'short_text', 'label' => 'X', 'id' => 'a']]])
            ->assertStatus(409);
    }

    public function test_save_draft_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->patchJson("/api/v1/forms/{$form->id}/draft", ['fields' => []])
            ->assertStatus(404);
    }
}
