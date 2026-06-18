<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationRole;
use App\Modules\Organizations\Http\Requests\StoreMembershipRequest;
use App\Modules\Organizations\Http\Resources\MembershipResource;
use App\Shared\Audit\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class MembershipController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/organizations/{org}/memberships  (auth:sanctum + tenant middleware)
     *
     * List memberships for the resolved tenant organization.
     * Requires members.manage permission (enforced by MembershipPolicy::viewAny()).
     */
    public function index(string $orgId): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OrganizationMembership::class);

        $memberships = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $orgId)
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
        $this->authorize('create', OrganizationMembership::class);

        /** @var array{external_user_id: string, role_keys?: array<int, string>} $data */
        $data = $request->validated();

        // Create the membership with explicit organization_id
        $membership = OrganizationMembership::create([
            'organization_id' => $orgId,
            'external_user_id' => $data['external_user_id'],
            'status' => 'active',
        ]);

        // Attach roles if role_keys provided
        if (! empty($data['role_keys'])) {
            $roles = OrganizationRole::withoutGlobalScope('tenant')
                ->where('organization_id', $orgId)
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
                'organization_id' => $orgId,
                'external_user_id' => $data['external_user_id'],
            ],
        );

        return (new MembershipResource($membership))
            ->response()
            ->setStatusCode(201);
    }
}
