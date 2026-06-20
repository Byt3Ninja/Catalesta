<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Application\PublishForm;
use App\Modules\Forms\Domain\Exceptions\InvalidFormDefinitionException;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PublishFormTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PublishForm
    {
        return $this->app->make(PublishForm::class);
    }

    private function bootTenantWithForm(): Form
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        return Form::create(['program_id' => (string) Str::ulid(), 'name' => 'Intake']);
    }

    /** A valid definition using the enumerated field types. */
    private function validDefinition(): array
    {
        return [
            ['type' => 'short_text', 'label' => 'Name', 'required' => true],
            ['type' => 'single_select', 'label' => 'Stage', 'options' => ['idea', 'mvp']],
            ['type' => 'file_upload', 'label' => 'Deck'],
            ['type' => 'consent', 'label' => 'I agree'],
        ];
    }

    public function test_publishes_immutably_with_a_content_addressed_version_id(): void // AC-1
    {
        $form = $this->bootTenantWithForm();

        $version = $this->service()->handle($form, $this->validDefinition());

        $this->assertSame('published', $version->status->value);
        $this->assertSame(1, $version->version_number);
        $this->assertNotNull($version->published_at);
        $this->assertSame(64, strlen($version->content_hash), 'content_hash is a sha256 hex digest');
        $this->assertSame($version->id, $form->fresh()->current_published_version_id);
    }

    public function test_rejects_unknown_field_type(): void // AC-2
    {
        $form = $this->bootTenantWithForm();

        $this->expectException(InvalidFormDefinitionException::class);
        try {
            $this->service()->handle($form, [['type' => 'rating_stars', 'label' => 'X']]);
        } finally {
            $this->assertDatabaseCount('form_versions', 0);
        }
    }

    public function test_rejects_embedded_code_or_expression(): void // AC-2 / NFR-005
    {
        $form = $this->bootTenantWithForm();

        $this->expectException(InvalidFormDefinitionException::class);
        $this->service()->handle($form, [
            ['type' => 'number', 'label' => 'Score', 'expr' => 'system("rm -rf /")'],
        ]);
    }

    public function test_editing_a_published_form_creates_a_new_version_and_prior_stays_resolvable(): void // AC-3
    {
        $form = $this->bootTenantWithForm();
        $v1 = $this->service()->handle($form, $this->validDefinition());

        $changed = array_merge($this->validDefinition(), [['type' => 'long_text', 'label' => 'Pitch']]);
        $v2 = $this->service()->handle($form, $changed);

        $this->assertNotSame($v1->id, $v2->id);
        $this->assertNotSame($v1->content_hash, $v2->content_hash);
        $this->assertSame(2, $v2->version_number);
        $this->assertNotNull(FormVersion::find($v1->id), 'the prior version id remains resolvable');

        // A published version cannot be mutated in place.
        $this->expectException(VersionStateException::class);
        $v1->update(['definition' => []]);
    }

    public function test_identical_republish_is_idempotent(): void // ★ AC-5
    {
        $form = $this->bootTenantWithForm();
        $def = $this->validDefinition();

        $a = $this->service()->handle($form, $def);
        // Same logical content, keys reordered → must hash identically and dedupe.
        $reordered = array_map(fn (array $f) => array_reverse($f, true), $def);
        $b = $this->service()->handle($form, $reordered);

        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->content_hash, $b->content_hash);
        $this->assertDatabaseCount('form_versions', 1);
    }

    public function test_form_is_tenant_scoped(): void // ★ AC-4
    {
        $form = $this->bootTenantWithForm();
        $version = $this->service()->handle($form, $this->validDefinition());
        $hash = $version->content_hash;

        [$otherUser, $otherOrg] = $this->bootUserWithOrg('Other Org');
        $this->actingAsTenant($otherUser, $otherOrg);

        $this->assertSame(0, Form::count(), 'another tenant cannot see the form');
        $this->assertSame(0, FormVersion::where('content_hash', $hash)->count(), 'cannot enumerate by content hash across tenants');
    }
}
