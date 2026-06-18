<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Identity\Domain\Models\ProfileSnapshot;

final class CaptureProfileSnapshot
{
    /**
     * Capture an immutable profile snapshot with content hash.
     *
     * @param  array<string, mixed>  $payload
     */
    public function capture(
        ExternalUser $user,
        string $contextType,
        ?string $contextId,
        array $payload,
        string $consentReference
    ): ProfileSnapshot {
        $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return ProfileSnapshot::create([
            'external_user_id' => $user->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'profile_version' => $user->profile_version,
            'payload_json' => $payload,
            'consent_reference' => $consentReference,
            'hash' => hash('sha256', $canonical),
            'captured_at' => now(),
        ]);
    }
}
