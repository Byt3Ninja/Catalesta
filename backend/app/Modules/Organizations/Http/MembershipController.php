<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationRole;
use App\Modules\Organizations\Http\Requests\StoreMembershipRequest;
use App\Modules\Organizations\Http\Resources\MembershipResource;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MembershipController extends Controller
{
    use AuthorizesRequests;

    /**
     * Verify that the route-param org matches the tenant resolved from the
     * X-Organization-Id header.  This is a defense-in-depth guard that prevents
     * a user who is authorized in Org A from writing into Org B by crafting a
     * URL with a different org id while keeping a valid header for Org A.
     *
     * Platform admins (TenantContext::$platformAdmin) are exempt because they may
     * legitimately operate across organizations without a header.  All other callers
     * must have route == header org or receive 403.
     */
    private function assertRouteMatchesTenant(string $routeOrgId): void
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        $tenantOrgId = $tenantContext->organizationId();

        // Platform admins may omit the header and operate cross-org intentionally.
        if ($tenantOrgId === null && $tenantContext->can('__platform_admin_bypass__')) {
            // can() returns true for platformAdmin regardless of key — this is the
            // idiomatic way to check $platformAdmin without exposing the private field.
            return;
        }

        if ($tenantOrgId !== $routeOrgId) {
            abort(403, 'Route organization does not match authenticated tenant.');
        }
    }

    /**
     * GET /api/v1/organizations/{org}/memberships  (auth:sanctum + tenant middleware)
     *
     * List memberships for the resolved tenant organization.
     * Requires members.manage permission (enforced by MembershipPolicy::viewAny()).
     */
    public function index(string $orgId): AnonymousResourceCollection
    {
        // I1 defense-in-depth: route param must equal the header-resolved tenant org.
        $this->assertRouteMatchesTenant($orgId);

        $this->authorize('viewAny', OrganizationMembership::class);

        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        // Use the TenantContext org (the authorized one) as the authoritative org id.
        $authorizedOrgId = $tenantContext->organizationId() ?? $orgId;

        $memberships = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $authorizedOrgId)
            ->with('roles')
            ->get();

        return MembershipResource::collection($memberships);
    }

    /**
     * POST /api/v1/organizations/{org}/memberships  (auth:sanctum + tenant middleware)
     *
     * Add a user as a member of the resolved tenant organization.
     * Requires members.invite OR members.manage (enforced by MembershipPolicy::create()).
     */
    public function store(StoreMembershipRequest $request, AuditLogger $audit, string $orgId): JsonResponse
    {
        // I1 defense-in-depth: route param must equal the header-resolved tenant org.
        $this->assertRouteMatchesTenant($orgId);

        $this->authorize('create', OrganizationMembership::class);

        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        // Use the TenantContext org (the authorized one), never the raw route param.
        $authorizedOrgId = $tenantContext->organizationId() ?? $orgId;

        /** @var array{external_user_id: string, role_keys?: array<int, string>} $data */
        $data = $request->validated();

        // Validate role_keys against known roles for this org before writing anything
        if (! empty($data['role_keys'])) {
            $knownKeys = OrganizationRole::withoutGlobalScope('tenant')
                ->where('organization_id', $authorizedOrgId)
                ->pluck('key')
                ->all();

            $unknownKeys = array_diff($data['role_keys'], $knownKeys);

            if (! empty($unknownKeys)) {
                throw ValidationException::withMessages([
                    'role_keys' => 'Unknown role key for this organization.',
                ]);
            }
        }

        $membership = DB::transaction(function () use ($authorizedOrgId, $data, $audit): OrganizationMembership {
            // Create the membership using the TenantContext-authorized org id.
            $membership = OrganizationMembership::create([
                'organization_id' => $authorizedOrgId,
                'external_user_id' => $data['external_user_id'],
                'status' => 'active',
            ]);

            // Attach roles if role_keys provided
            if (! empty($data['role_keys'])) {
                $roles = OrganizationRole::withoutGlobalScope('tenant')
                    ->where('organization_id', $authorizedOrgId)
                    ->whereIn('key', $data['role_keys'])
                    ->get();

                $membership->roles()->attach($roles->pluck('id')->toArray());
            }

            $membership->load('roles');

            $audit->record(
                'membership.created',
                'organization_membership',
                $membership->id,
                [],
                [
                    'organization_id' => $authorizedOrgId,
                    'external_user_id' => $data['external_user_id'],
                ],
            );

            return $membership;
        });

        return (new MembershipResource($membership))
            ->response()
            ->setStatusCode(201);
    }
}
