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
        $this->assertStringContainsString('T', $out['created_at']);
    }

    public function test_form_resource_derives_draft_pointer_and_published_ids(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $form = Form::create(['name' => 'Intake']);

        // Create published versions in non-sequential order (v3, v1, v2)
        // to verify ordering is by version_number, not insertion order
        $publishedVersion3 = FormVersion::create([
            'form_id' => $form->id, 'status' => 'published', 'version_number' => 3,
            'content_hash' => str_repeat('c', 64), 'definition' => [], 'published_at' => now(),
        ]);
        $publishedVersion1 = FormVersion::create([
            'form_id' => $form->id, 'status' => 'published', 'version_number' => 1,
            'content_hash' => str_repeat('a', 64), 'definition' => [], 'published_at' => now(),
        ]);
        $publishedVersion2 = FormVersion::create([
            'form_id' => $form->id, 'status' => 'published', 'version_number' => 2,
            'content_hash' => str_repeat('b', 64), 'definition' => [], 'published_at' => now(),
        ]);
        $draft = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $out = (new FormResource($form->load('versions')))->toArray(Request::create('/'));

        $this->assertSame($form->id, $out['id']);
        $this->assertSame('Intake', $out['name']);
        $this->assertNull($out['description']);
        $this->assertSame(3, $out['latest_version']);
        $this->assertSame(
            [$publishedVersion1->id, $publishedVersion2->id, $publishedVersion3->id],
            $out['published_version_ids'],
            'published_version_ids must be ordered by version_number ascending'
        );
        $this->assertSame($draft->id, $out['current_draft_version_id']);
    }
}
