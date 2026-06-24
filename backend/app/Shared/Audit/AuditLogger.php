<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use App\Shared\Support\CorrelationId;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;

// Not final: the container swaps in a throwing double in tests to exercise the
// best-effort audit path (RA.2). It remains a single concrete service otherwise.
class AuditLogger
{
    public function __construct(private TenantContext $tenant, private Request $request) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(string $action, ?string $targetType, ?string $targetId, array $before = [], array $after = [], string $result = 'success', ?string $organizationId = null, ?string $actorAccountId = null): AuditLog
    {
        return AuditLog::create([
            // Explicit actor wins (RA.2 threads the Gate-resolved user, which is
            // authoritative for an authorization decision); else the request user.
            'actor_account_id' => $actorAccountId ?? optional($this->request->user())->id,
            // Explicit org wins (a public applicant has no TenantContext, so the
            // submit audits under the COHORT's org, Story 2.7); else resolved tenant.
            'organization_id' => $organizationId ?? $this->tenant->organizationId(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip_address' => $this->request->ip(),
            'correlation_id' => CorrelationId::get(),
            'result' => $result,
        ]);
    }
}
