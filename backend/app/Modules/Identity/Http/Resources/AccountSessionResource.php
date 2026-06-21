<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The canonical session-user payload. Wrapped as { "user": {...} } to match the
 * existing /auth/callback and /auth/session response shape consumed by the SPA.
 */
final class AccountSessionResource extends JsonResource
{
    public static $wrap = 'user';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Account $a */
        $a = $this->resource;

        return [
            'id' => $a->id,
            'email' => $a->email,
            'display_name' => $a->display_name,
            'email_verified' => $a->hasVerifiedEmail(),
            'startup_gate_subject_id' => $a->startupGateSubjectId(),
            'linked_providers' => $a->linkedIdentities()->pluck('provider')->all(),
            'has_password' => $a->password !== null,
        ];
    }
}
