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
    public function record(string $action, ?string $targetType, ?string $targetId, array $before = [], array $after = [], string $result = 'success'): AuditLog
    {
        return AuditLog::create([
            'actor_external_user_id' => optional($this->request->user())->id,
            'organization_id' => $this->tenant->organizationId(),
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
