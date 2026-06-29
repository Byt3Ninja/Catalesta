<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Application\CreateForm;
use App\Modules\Forms\Application\ForkFormDraft;
use App\Modules\Forms\Application\PublishForm;
use App\Modules\Forms\Application\SaveFormDraft;
use App\Modules\Forms\Domain\Exceptions\NoDraftToPublishException;
use App\Modules\Forms\Domain\Models\Form;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublishFormTest extends TestCase
{
    use RefreshDatabase;

    private function definition(): array
    {
        return [
            ['type' => 'short_text', 'label' => 'Name', 'id' => 'f1', 'required' => true],
            ['type' => 'single_select', 'label' => 'Stage', 'id' => 'f2', 'options' => ['idea', 'mvp']],
        ];
    }

    /** Create a form (seeds draft) under tenant context and save a draft definition. */
    private function formWithDraft(array $fields): Form
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = $this->app->make(CreateForm::class)->handle('Intake');
        $this->app->make(SaveFormDraft::class)->handle($form, $fields);

        return $form->refresh();
    }

    public function test_publishes_the_draft_immutably_with_a_content_hash(): void
    {
        $form = $this->formWithDraft($this->definition());

        $version = $this->app->make(PublishForm::class)->handle($form);

        $this->assertSame('published', $version->status->value);
        $this->assertSame(1, $version->version_number);
        $this->assertNotNull($version->published_at);
        $this->assertSame(64, strlen((string) $version->content_hash));
        $this->assertSame($version->id, $form->fresh()->current_published_version_id);
        $this->assertNull($form->fresh()->draftVersion(), 'the draft was promoted, leaving no open draft');

        $this->expectException(VersionStateException::class);
        $version->update(['definition' => []]);
    }

    public function test_publish_with_no_draft_throws(): void
    {
        $form = $this->formWithDraft($this->definition());
        $this->app->make(PublishForm::class)->handle($form); // promotes the only draft

        $this->expectException(NoDraftToPublishException::class);
        $this->app->make(PublishForm::class)->handle($form->refresh());
    }

    public function test_publish_of_empty_draft_throws(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = $this->app->make(CreateForm::class)->handle('Intake'); // empty draft

        $this->expectException(NoDraftToPublishException::class);
        $this->app->make(PublishForm::class)->handle($form);
    }

    public function test_identical_republish_is_idempotent(): void
    {
        $this->markTestSkipped('depends on ForkFormDraft (Task 8)');

        $form = $this->formWithDraft($this->definition());
        $a = $this->app->make(PublishForm::class)->handle($form);

        // fork a new draft with the same content, then republish → same version, no new row
        $this->app->make(ForkFormDraft::class)->handle($form->refresh(), $a->id);
        $b = $this->app->make(PublishForm::class)->handle($form->refresh());

        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('form_versions', 1);
    }
}
