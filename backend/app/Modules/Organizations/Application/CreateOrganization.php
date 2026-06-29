<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Application;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationPermission;
use App\Modules\Organizations\Domain\Models\OrganizationRole;
use App\Shared\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    public function handle(Account $creator, string $name, array $branding = []): Organization
    {
        try {
            return $this->create($creator, $name, $branding);
        } catch (UniqueConstraintViolationException) {
            // Concurrent same-name race that slipped past the FormRequest uniqueness
            // check: surface the same clean 422 envelope as the request rule.
            throw ValidationException::withMessages([
                'name' => ['An organization with a similar name already exists.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    private function create(Account $creator, string $name, array $branding): Organization
    {
        return DB::transaction(function () use ($creator, $name, $branding): Organization {
            // Step 1: Create the organization
            $orgData = ['name' => $name];
            if (! empty($branding)) {
                $orgData['branding'] = $branding;
            }

            $org = Organization::create($orgData);

            // Step 2: Create the owner role — organization_id set via direct assignment
            // (organization_id is intentionally excluded from $fillable; must be set directly)
            $ownerRole = new OrganizationRole(['key' => 'owner', 'name' => 'Owner', 'is_system' => true]);
            $ownerRole->organization_id = $org->id;
            $ownerRole->save();

            // Step 3: Attach all permissions from the catalog to the owner role
            $permissionIds = OrganizationPermission::whereIn('key', [
                'organizations.manage',
                'members.manage',
                'members.invite',
                'roles.manage',
                'programs.manage',
                'programs.publish',
                'cohorts.manage',
                'stages.manage',
                'forms.manage',
            ])->pluck('id')->toArray();

            $ownerRole->permissions()->sync($permissionIds);

            // Step 4: Create the creator's active membership — organization_id set via direct assignment
            // (organization_id is intentionally excluded from $fillable; must be set directly)
            $membership = new OrganizationMembership(['account_id' => $creator->id, 'status' => 'active']);
            $membership->organization_id = $org->id;
            $membership->save();

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
