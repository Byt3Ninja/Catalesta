<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use App\Shared\Support\CorrelationId;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function __construct(private TenantContext $tenant, private Request $request) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(string $action, ?string $targetType, ?string $targetId, array $before = [], array $after = [], string $result = 'success', ?string $organizationId = null): AuditLog
    {
        return AuditLog::create([
            'actor_external_user_id' => optional($this->request->user())->id,
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
