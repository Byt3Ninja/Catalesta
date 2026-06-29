<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\Exceptions\InvalidFormDefinitionException;
use App\Modules\Forms\Domain\Exceptions\NoDraftToPublishException;
use App\Modules\Forms\Domain\FormDefinitionValidator;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;

final class SaveFormDraft
{
    public function __construct(private readonly FormDefinitionValidator $validator) {}

    /**
     * @param  array<int, array<string, mixed>>  $fields
     *
     * @throws NoDraftToPublishException when the form has no draft version
     * @throws InvalidFormDefinitionException
     */
    public function handle(Form $form, array $fields): FormVersion
    {
        /** @var FormVersion|null $draft */
        $draft = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', 'draft')
            ->first();

        if ($draft === null) {
            throw new NoDraftToPublishException('This form has no draft version to edit.');
        }

        if ($fields !== []) {
            $this->validator->validate($fields); // type + no-code enforcement (NFR-005)
        }

        $draft->definition = $fields;
        $draft->save();

        return $draft;
    }
}
