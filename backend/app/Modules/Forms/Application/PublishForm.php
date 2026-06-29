<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\Exceptions\NoDraftToPublishException;
use App\Modules\Forms\Domain\FormDefinitionValidator;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the form's single draft version as an immutable, content-addressed
 * version. The version id is sha256 of the canonical (key-sorted) definition;
 * republishing content identical to an existing published version returns that
 * version and discards the redundant draft (idempotent, no duplicate row).
 */
final class PublishForm
{
    public function __construct(
        private readonly FormDefinitionValidator $validator,
        private readonly VersionPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws NoDraftToPublishException when there is no draft, or the draft is empty
     */
    public function handle(Form $form): FormVersion
    {
        /** @var FormVersion|null $draft */
        $draft = FormVersion::query()
            ->where('form_id', $form->id)
            ->where('status', 'draft')
            ->first();

        if ($draft === null || $draft->definition === []) {
            throw new NoDraftToPublishException('This form has no publishable draft.');
        }

        $this->validator->validate($draft->definition);
        $hash = hash('sha256', $this->validator->canonicalJson($draft->definition));

        $version = DB::transaction(function () use ($form, $draft, $hash): FormVersion {
            /** @var FormVersion|null $existing */
            $existing = FormVersion::query()
                ->where('form_id', $form->id)
                ->where('status', 'published')
                ->where('content_hash', $hash)
                ->first();

            if ($existing !== null) {
                $draft->delete();                 // discard redundant draft (avoids UNIQUE collision)
                $form->update(['current_published_version_id' => $existing->id]);

                return $existing;
            }

            $draft->content_hash = $hash;         // still a draft row — mutation allowed
            $draft->save();
            $this->publisher->publish($draft);    // version_number, Published, published_at
            $form->update(['current_published_version_id' => $draft->id]);

            return $draft->refresh();
        });

        $this->audit->record('form.published', 'form_version', $version->id, [], [
            'content_hash' => $hash,
            'version_number' => $version->version_number,
        ]);

        return $version;
    }
}
