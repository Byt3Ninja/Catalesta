<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Organizations\Application\CreateOrganization;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Organizations\Domain\Models\OrganizationRole;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dev-only sample logins for exploring the operator console end-to-end.
 *
 * NOT registered in DatabaseSeeder — run explicitly:
 *   php artisan db:seed --class=Database\\Seeders\\SampleUsersSeeder
 *
 * Creates three verified, password-auth accounts that all share one
 * organization ("Acme Incubator") with the system Owner role (every catalog
 * permission), so each can log in and exercise programs/cohorts immediately.
 * The shared DEV password is intentionally hardcoded — this is throwaway local
 * sample data, never run in production.
 *
 * Idempotent: re-running reconciles accounts/membership without duplicating.
 */
final class SampleUsersSeeder extends Seeder
{
    /** Throwaway local password for every sample login. */
    private const PASSWORD = 'Password123!';

    private const ORG_NAME = 'Acme Incubator';

    /** @var list<array{email: string, name: string}> */
    private const USERS = [
        ['email' => 'alice@catalesta.test', 'name' => 'Alice Owner'],
        ['email' => 'bob@catalesta.test', 'name' => 'Bob Operator'],
        ['email' => 'carol@catalesta.test', 'name' => 'Carol Operator'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('SampleUsersSeeder skipped: refusing to seed sample logins in production.');

            return;
        }

        // Owner-role provisioning syncs against the permission catalog.
        $this->call(PermissionCatalogSeeder::class);

        $accounts = [];
        foreach (self::USERS as $spec) {
            $accounts[] = Account::firstOrCreate(
                ['email' => $spec['email']],
                [
                    'display_name' => $spec['name'],
                    'password' => self::PASSWORD, // 'hashed' cast hashes on assignment
                    'email_verified_at' => now(),
                ],
            );
        }

        $owner = $accounts[0];
        $members = array_slice($accounts, 1);

        // First account owns the org (CreateOrganization = the real onboarding path:
        // org + Owner role w/ all permissions + active creator membership).
        $org = Organization::where('name', self::ORG_NAME)->first();
        if ($org === null) {
            $org = app(CreateOrganization::class)->handle($owner, self::ORG_NAME);
        }

        // OrganizationRole/OrganizationMembership are tenant-scoped; a CLI seeder
        // has no resolved tenant, so reads must run as system (as the org index
        // controller does) or the scope hides the rows.
        app(TenantContext::class)->runAsSystem(function () use ($org, $members): void {
            $ownerRole = OrganizationRole::query()
                ->where('organization_id', $org->id)
                ->where('key', 'owner')
                ->firstOrFail();

            // Remaining accounts join the same org with the Owner role (full access),
            // mirroring CreateOrganization steps 4–5 for additional members. Set
            // organization_id directly — it is excluded from $fillable.
            foreach ($members as $member) {
                DB::transaction(function () use ($member, $org, $ownerRole): void {
                    $membership = OrganizationMembership::query()
                        ->where('organization_id', $org->id)
                        ->where('account_id', $member->id)
                        ->first();

                    if ($membership === null) {
                        $membership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
                        $membership->organization_id = $org->id;
                        $membership->save();
                    }

                    $membership->roles()->syncWithoutDetaching([$ownerRole->id]);
                });
            }
        });

        $this->command->info(sprintf(
            'Seeded %d sample logins into "%s" (password: %s):',
            count($accounts),
            self::ORG_NAME,
            self::PASSWORD,
        ));
        foreach (self::USERS as $spec) {
            $this->command->line("  - {$spec['email']}");
        }
    }
}
