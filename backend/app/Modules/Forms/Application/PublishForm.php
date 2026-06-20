<?php

declare(strict_types=1);

namespace App\Modules\Forms\Application;

use App\Modules\Forms\Domain\FormDefinitionValidator;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Validates a form definition and publishes it as an immutable, content-addressed
 * version. The version id is sha256 of the canonical (key-sorted) definition;
 * republishing identical content returns the existing version (idempotent), so a
 * given logical form has exactly one version row per content hash.
 */
final class PublishForm
{
    public function __construct(
        private readonly FormDefinitionValidator $validator,
        private readonly VersionPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $definition
     */
    public function handle(Form $form, array $definition): FormVersion
    {
        $this->validator->validate($definition);
        $hash = hash('sha256', $this->validator->canonicalJson($definition));

        $version = DB::transaction(function () use ($form, $definition, $hash): FormVersion {
            $existing = FormVersion::where('form_id', $form->id)->where('content_hash', $hash)->first();
            if ($existing !== null) {
                return $existing; // idempotent republish — no duplicate version
            }

            $version = FormVersion::create([
                'form_id' => $form->id,
                'content_hash' => $hash,
                'definition' => $definition,
            ]);

            $this->publisher->publish($version); // assigns version_number, Published, published_at
            $form->update(['current_published_version_id' => $version->id]);

            return $version->refresh();
        });

        $this->audit->record('form.published', 'form_version', $version->id, [], [
            'content_hash' => $hash,
            'version_number' => $version->version_number,
        ]);

        return $version;
    }
}
