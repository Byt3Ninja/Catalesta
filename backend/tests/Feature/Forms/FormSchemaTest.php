<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FormSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_form_can_be_created_without_a_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $form = Form::create(['name' => 'Intake']);

        $this->assertNull($form->program_id);
    }

    public function test_a_draft_version_can_be_stored_without_a_content_hash(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $form = Form::create(['name' => 'Intake']);
        $draft = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $this->assertNull($draft->content_hash);
        $this->assertSame('draft', $draft->status->value);
        $this->assertSame(0, $draft->version_number);
    }
}
