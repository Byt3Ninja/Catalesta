<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Domain\Models\ProfileSnapshot;

final class CaptureProfileSnapshot
{
    /**
     * Capture an immutable profile snapshot with content hash.
     *
     * @param  array<string, mixed>  $payload
     */
    public function capture(
        Account $account,
        string $contextType,
        ?string $contextId,
        array $payload,
        string $consentReference,
        int $profileVersion = 0
    ): ProfileSnapshot {
        $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return ProfileSnapshot::create([
            'account_id' => $account->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'profile_version' => $profileVersion,
            'payload_json' => $payload,
            'consent_reference' => $consentReference,
            'hash' => hash('sha256', $canonical),
            'captured_at' => now(),
        ]);
    }
}
