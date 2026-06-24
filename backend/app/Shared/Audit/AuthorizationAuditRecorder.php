<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Story RA.2 (slice 1): enforced audit of authorization decisions.
 *
 * Registers a Gate::after hook that records authorization outcomes to the
 * append-only audit_logs substrate. The AC names a "middleware", but HTTP
 * middleware cannot observe individual `$this->authorize` / `Gate::authorize`
 * calls made inside controllers — Gate::after is the correct hook (it fires
 * after every gate check with the result). See ADR-0010 (substrate home).
 *
 * What it records (slice 1, per 2026-06-23 scoping decision):
 *   - every DENIAL (the security signal), and
 *   - every ALLOW on a non-read (mutating/sensitive) ability.
 * Read allows (view/viewAny) are intentionally skipped to keep audit_logs
 * meaningful. Recording every allow, outbox-queued writes, and the failure
 * circuit-breaker are deferred to a follow-on slice (RA.4 generalizes outbox).
 *
 * Best-effort: a write failure is reported and swallowed — auditing must never
 * change the authorization outcome or break the request.
 */
final class AuthorizationAuditRecorder
{
    /** Read-only abilities whose ALLOW outcome is not worth recording. */
    private const READ_ABILITIES = ['view', 'viewAny'];

    public function register(): void
    {
        Gate::after(function (?Authenticatable $user, string $ability, ?bool $result, array $arguments): void {
            // null = the gate abstained (no policy opinion); not a decision to record.
            if ($result === null) {
                return;
            }

            // Allows on read abilities are high-volume noise — skip them.
            if ($result === true && in_array($ability, self::READ_ABILITIES, true)) {
                return;
            }

            $this->record($user, $ability, $result, $arguments);
        });
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function record(?Authenticatable $user, string $ability, bool $allowed, array $arguments): void
    {
        try {
            $target = $arguments[0] ?? null;
            $targetType = $target instanceof Model ? $target::class : null;
            $targetId = $target instanceof Model ? (string) $target->getKey() : null;

            app(AuditLogger::class)->record(
                action: 'authz.'.$ability,
                targetType: $targetType,
                targetId: $targetId,
                result: $allowed ? 'allowed' : 'denied',
                actorAccountId: $user?->getAuthIdentifier() !== null ? (string) $user->getAuthIdentifier() : null,
            );
        } catch (\Throwable $e) {
            // Best-effort: auditing never blocks the authorization decision.
            try {
                report($e);
            } catch (\Throwable) {
                // nothing else safe to do
            }
        }
    }
}
