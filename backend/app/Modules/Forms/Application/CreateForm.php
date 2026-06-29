<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Support\Facades\DB;

/**
 * Creates an org-scoped form (program optional) and seeds its single empty draft
 * version. The draft is intentionally not run through FormDefinitionValidator —
 * an empty working copy is valid until publish.
 */
final class CreateForm
{
    public function handle(string $name): Form
    {
        return DB::transaction(function () use ($name): Form {
            $form = Form::create(['name' => $name]);
            FormVersion::create(['form_id' => $form->id, 'status' => 'draft', 'version_number' => 0, 'definition' => []]);

            return $form->load('versions');
        });
    }
}
