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
            ['key' => 'programs.manage',      'description' => 'Manage programs'],
            ['key' => 'programs.publish',     'description' => 'Publish programs'],
            ['key' => 'cohorts.manage',       'description' => 'Manage cohorts'],
            ['key' => 'stages.manage',        'description' => 'Manage stages'],
            ['key' => 'forms.manage',         'description' => 'Manage forms'],
            ['key' => 'assessments.manage',   'description' => 'Manage scoring models and evaluations'],
        ];

        foreach ($permissions as $data) {
            OrganizationPermission::firstOrCreate(
                ['key' => $data['key']],
                ['description' => $data['description']],
            );
        }
    }
}
