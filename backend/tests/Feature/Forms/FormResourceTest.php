<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Modules\Forms\Http\Resources\FormResource;
use App\Modules\Forms\Http\Resources\FormVersionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class FormResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_resource_renames_fields_to_the_fe_contract(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        $draft = FormVersion::create([
            'form_id' => $form->id,
            'definition' => [['type' => 'short_text', 'label' => 'Name', 'id' => 'f1']],
        ]);

        $out = (new FormVersionResource($draft))->toArray(Request::create('/'));

        $this->assertSame($draft->id, $out['id']);
        $this->assertSame($form->id, $out['form_id']);
        $this->assertSame(0, $out['version']);
        $this->assertSame('draft', $out['status']);
        $this->assertSame([['type' => 'short_text', 'label' => 'Name', 'id' => 'f1']], $out['fields']);
        $this->assertNull($out['published_at']);
    }

    public function test_form_resource_derives_draft_pointer_and_published_ids(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);
        $published = FormVersion::create([
            'form_id' => $form->id, 'status' => 'published', 'version_number' => 1,
            'content_hash' => str_repeat('a', 64), 'definition' => [], 'published_at' => now(),
        ]);
        $draft = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $out = (new FormResource($form->load('versions')))->toArray(Request::create('/'));

        $this->assertNull($out['description']);
        $this->assertSame(1, $out['latest_version']);
        $this->assertSame([$published->id], $out['published_version_ids']);
        $this->assertSame($draft->id, $out['current_draft_version_id']);
    }
}
