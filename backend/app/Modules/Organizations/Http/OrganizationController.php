<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Http;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Organizations\Application\CreateOrganization;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Http\Requests\StoreOrganizationRequest;
use App\Modules\Organizations\Http\Requests\UpdateOrganizationRequest;
use App\Modules\Organizations\Http\Resources\OrganizationResource;
use App\Shared\Audit\AuditLogger;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OrganizationController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/v1/organizations  (auth:sanctum, no tenant middleware)
     *
     * List organizations the authenticated user has an active membership in.
     * Platform admins see all organizations.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Account $user */
        $user = $request->user();

        if ($user->is_platform_admin) {
            $orgs = Organization::all();
        } else {
            $orgIds = app(TenantContext::class)->runAsSystem(fn () => OrganizationMembership::query()
                ->where('account_id', $user->id)
                ->where('status', 'active')
                ->pluck('organization_id')
                ->toArray());

            $orgs = Organization::whereIn('id', $orgIds)->get();
        }

        return OrganizationResource::collection($orgs);
    }

    /**
     * POST /api/v1/organizations  (auth:sanctum, no tenant middleware)
     *
     * Create a new organization. The authenticated user becomes the owner.
     * No tenant context is set at this point — CreateOrganization handles that.
     */
    public function store(StoreOrganizationRequest $request, CreateOrganization $service): JsonResponse
    {
        /** @var Account $user */
        $user = $request->user();

        /** @var array{name: string, branding?: array<string, mixed>|null} $data */
        $data = $request->validated();

        $org = $service->handle(
            $user,
            $data['name'],
            $data['branding'] ?? [],
        );

        return (new OrganizationResource($org))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/organizations/{id}  (auth:sanctum + tenant middleware)
     *
     * Show a single organization. The tenant middleware already verified active membership.
     * OrganizationPolicy::view() always returns true — membership = permission to view.
     */
    public function show(string $id): OrganizationResource
    {
        $this->assertResolvedOrg($id);

        $org = Organization::findOrFail($id);

        $this->authorize('view', $org);

        return new OrganizationResource($org);
    }

    /**
     * PATCH /api/v1/organizations/{id}  (auth:sanctum + tenant middleware)
     *
     * Update organization name and/or branding.
     * Requires organizations.manage permission (enforced by OrganizationPolicy::update()).
     */
    public function update(UpdateOrganizationRequest $request, AuditLogger $audit, string $id): OrganizationResource
    {
        $this->assertResolvedOrg($id);

        $org = Organization::findOrFail($id);

        $this->authorize('update', $org);

        /** @var array{name?: string, branding?: array<string, mixed>|null} $data */
        $data = $request->validated();

        $before = $org->only(['name', 'branding']);

        if (isset($data['name'])) {
            $org->name = $data['name'];
        }

        if (array_key_exists('branding', $data)) {
            $org->branding = $data['branding'];
        }

        $org->save();

        $after = $org->only(['name', 'branding']);

        $audit->record(
            'organization.updated',
            'organization',
            $org->id,
            $before,
            $after,
        );

        return new OrganizationResource($org);
    }

    /**
     * Neutral 404 (FR-004 / AR-6): a tenant-resolved (non-platform-admin) user may only
     * access the org id matching their resolved context. Passing a foreign org id in the
     * URL (with their own valid header) must not reveal that org exists.
     */
    private function assertResolvedOrg(string $id): void
    {
        $tenant = app(TenantContext::class);

        if ($tenant->has() && $id !== $tenant->organizationId()) {
            throw new NotFoundHttpException;
        }
    }
}
