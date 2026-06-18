<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ResolveTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $request->user();
        $isPlatformAdmin = (bool) ($user?->is_platform_admin);
        if ($isPlatformAdmin) {
            $this->tenant->actingAsPlatformAdmin(true);
        }

        $orgId = $request->header((string) config('tenancy.header'));
        if (! $orgId) {
            if ($isPlatformAdmin) {
                return $next($request);
            }
            throw new BadRequestHttpException('Missing organization header.');
        }

        $membership = $user
            ? $this->tenant->runAsSystem(fn () => OrganizationMembership::query()
                ->where('organization_id', $orgId)
                ->where('external_user_id', $user->id)
                ->where('status', 'active')
                ->first())
            : null;

        if (! $membership) {
            if ($isPlatformAdmin) {
                return $next($request);
            }
            throw new AccessDeniedHttpException('Not a member of this organization.');
        }

        $this->tenant->setOrganization($orgId, $membership, $membership->effectivePermissionKeys());

        return $next($request);
    }
}
