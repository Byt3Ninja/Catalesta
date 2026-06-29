<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ForkFormDraft
{
    /**
     * @throws ModelNotFoundException when $fromVersionId
     *                                is not a published version of $form
     */
    public function handle(Form $form, string $fromVersionId): FormVersion
    {
        // Always validate that $fromVersionId is a published version of this form.
        // This must happen before the draft short-circuit so an invalid version id
        // still returns 404 even when a draft already exists.
        /** @var FormVersion $source */
        $source = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', 'published')
            ->findOrFail($fromVersionId);

        // Invariant: at most one draft per form. Return any existing draft unchanged.
        /** @var FormVersion|null $existingDraft */
        $existingDraft = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', 'draft')
            ->first();

        if ($existingDraft !== null) {
            return $existingDraft;
        }

        return FormVersion::create([
            'form_id' => $form->id,
            'definition' => json_decode(json_encode($source->definition), true), // deep copy
        ]);
    }
}
