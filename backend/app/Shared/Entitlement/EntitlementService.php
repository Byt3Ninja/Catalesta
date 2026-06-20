<?php

declare(strict_types=1);

namespace App\Shared\Entitlement;

/**
 * Entitlement seam (FR-060). Domain modules gate actions through this interface
 * only — never by inspecting plan names. P1a ships the allow-all socket; the real
 * limit policy (FR-061 counter) lands in P1b behind the same interface.
 */
interface EntitlementService
{
    /**
     * Assert the current tenant may perform $action (e.g. 'cohort.open'). The
     * P1a socket never blocks; P1b throws when a limit is reached.
     */
    public function check(string $action): void;
}
