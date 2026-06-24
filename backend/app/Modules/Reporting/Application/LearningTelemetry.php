<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application;

use App\Modules\Reporting\Domain\Models\LearningEvent;

/**
 * Records Learning Telemetry events (FR-080). Best-effort by design: telemetry is
 * high-volume and undercount-tolerant ("views are approximate"), so a write
 * failure is reported and swallowed — it must NEVER break the request path
 * (especially the public apply page). organization_id is passed explicitly (the
 * cohort's org): public emit points have no TenantContext, exactly like the
 * applicant submit/audit path (AuditLogger's explicit-org contract). Deliberately
 * NOT idempotent — routing pageviews through the idempotency kernel is the wrong
 * tradeoff; the funnel's `viewed >= started` clamp absorbs the lossiness.
 *
 * Lives in the Reporting module (it owns LearningEvent) rather than app/Shared/:
 * a cross-cutting class must not depend on a domain model (ADR-0010; enforced by
 * deptrac). Cohorts' apply page calls it as a normal cross-module dependency.
 */
final class LearningTelemetry
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $eventName, string $cohortId, string $organizationId, array $payload = []): ?LearningEvent
    {
        try {
            $event = new LearningEvent([
                'cohort_id' => $cohortId,
                'event_name' => $eventName,
                'payload' => $payload ?: null,
                'occurred_at' => now(),
            ]);
            // Explicit org (public events have no tenant context); BelongsToTenant's
            // creating hook accepts an explicitly-set org when no context is present.
            $event->setAttribute('organization_id', $organizationId);
            $event->save();

            return $event;
        } catch (\Throwable $e) {
            // best-effort: a telemetry failure never breaks the request. report()
            // itself runs the exception pipeline (log/Sentry/…), which can throw —
            // guard it too so this method honours its "never throws" contract.
            try {
                report($e);
            } catch (\Throwable) {
                // nothing else we can safely do on the public path
            }

            return null;
        }
    }
}
