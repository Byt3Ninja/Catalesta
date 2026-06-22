<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmailVerifiedGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_native_account_cannot_create_org(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $a = Account::create(['email' => 'unv@example.com', 'password' => 'super-secret', 'email_verified_at' => null]);

        $this->actingAs($a, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Acme'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_verified_native_account_can_create_org(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $a = Account::create(['email' => 'ver@example.com', 'password' => 'super-secret', 'email_verified_at' => now()]);

        $this->actingAs($a, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'Verified Co'])
            ->assertStatus(201);
    }

    public function test_sg_account_passes_gate(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $a = $this->makeAccount(); // verified by the seam (Task 1)

        $this->actingAs($a, 'web')
            ->postJson('/api/v1/organizations', ['name' => 'SG Co'])
            ->assertStatus(201);
    }
}
