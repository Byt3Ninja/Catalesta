<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Organizations\Domain\Models\OrganizationPermission;
use Illuminate\Database\Seeder;

class PermissionCatalogSeeder extends Seeder
{
    /**
     * Idempotently seed the global permission catalog.
     * Uses upsert-by-key so it is safe to run multiple times.
     */
    public function run(): void
    {
        $permissions = [
            ['key' => 'organizations.manage', 'description' => 'Manage organization settings'],
            ['key' => 'members.manage',       'description' => 'Manage organization members'],
            ['key' => 'members.invite',       'description' => 'Invite new members to the organization'],
            ['key' => 'roles.manage',         'description' => 'Manage organization roles and permissions'],
        ];

        foreach ($permissions as $data) {
            OrganizationPermission::firstOrCreate(
                ['key' => $data['key']],
                ['description' => $data['description']],
            );
        }
    }
}
