<?php

declare(strict_types=1);

namespace App\Shared\Entitlement;

/**
 * P1a allow-all entitlement socket (FR-060): every action is permitted. The
 * enforced call sites (program.publish, cohort.open, application.submit) route
 * through here so P1b can swap in the real counter without touching them.
 */
final class AllowAllEntitlementService implements EntitlementService
{
    public function check(string $action): void
    {
        // allow-all in P1a — no limit policy until P1b (FR-061).
    }
}
