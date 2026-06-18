<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Application;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationPermission;
use App\Modules\Organizations\Domain\Models\OrganizationRole;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class CreateOrganization
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Create an organization with owner role, all permissions, and active creator membership.
     *
     * NOTE: TenantContext has() is FALSE here because no tenant has been resolved yet.
     * BelongsToTenant::creating() hook will NOT auto-set organization_id.
     * We MUST set organization_id explicitly on all tenant-owned records.
     *
     * @param  array<string, mixed>  $branding
     */
    public function handle(ExternalUser $creator, string $name, array $branding = []): Organization
    {
        return DB::transaction(function () use ($creator, $name, $branding): Organization {
            // Step 1: Create the organization
            $orgData = ['name' => $name];
            if (! empty($branding)) {
                $orgData['branding'] = $branding;
            }

            $org = Organization::create($orgData);

            // Step 2: Create the owner role — organization_id set EXPLICITLY
            $ownerRole = OrganizationRole::create([
                'organization_id' => $org->id,
                'key' => 'owner',
                'name' => 'Owner',
                'is_system' => true,
            ]);

            // Step 3: Attach all 4 permissions from the catalog to the owner role
            $permissionIds = OrganizationPermission::whereIn('key', [
                'organizations.manage',
                'members.manage',
                'members.invite',
                'roles.manage',
            ])->pluck('id')->toArray();

            $ownerRole->permissions()->sync($permissionIds);

            // Step 4: Create the creator's active membership — organization_id set EXPLICITLY
            $membership = OrganizationMembership::create([
                'organization_id' => $org->id,
                'external_user_id' => $creator->id,
                'status' => 'active',
            ]);

            // Step 5: Attach the owner role to the membership
            $membership->roles()->attach($ownerRole->id);

            // Step 6: Audit (organization_id will be null in AuditLog since TenantContext is not set — acceptable)
            $this->audit->record(
                'organization.created',
                'organization',
                $org->id,
                [],
                ['name' => $org->name],
            );

            return $org;
        });
    }
}
