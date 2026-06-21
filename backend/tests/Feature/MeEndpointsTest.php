<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Domain\Models\LinkedIdentity;
use App\Modules\Identity\Domain\Models\LinkedIdentityToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MeEndpointsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function createUserWithToken(bool $expired = false): Account
    {
        $user = Account::create([
            'email' => 'me@example.com',
            'display_name' => 'Me User',
            'avatar_url' => 'https://example.com/avatar.png',
            'locale' => 'en',
        ]);

        $link = LinkedIdentity::create([
            'account_id' => $user->id,
            'provider' => 'startup_gate',
            'subject_id' => 'sg_me_test_01',
            'linked_at' => now(),
            'synchronization_status' => 'synced',
            'synchronized_at' => now(),
        ]);

        LinkedIdentityToken::create([
            'linked_identity_id' => $link->id,
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => $expired ? now()->subMinute() : now()->addHour(),
        ]);

        return $user;
    }

    // ---------------------------------------------------------------------------
    // GET /api/v1/me — local projection, no HTTP call
    // ---------------------------------------------------------------------------

    public function test_me_returns_local_projection(): void
    {
        $user = $this->createUserWithToken();
        $this->actingAs($user, 'web');

        Http::preventStrayRequests(); // ensure NO external HTTP call is made

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'startup_gate_subject_id', 'email', 'display_name', 'avatar_url', 'locale']])
            ->assertJsonPath('data.startup_gate_subject_id', 'sg_me_test_01')
            ->assertJsonPath('data.email', 'me@example.com')
            ->assertJsonPath('data.display_name', 'Me User');
    }

    public function test_me_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------------
    // GET /api/v1/me/profile — passthrough to profile API
    // ---------------------------------------------------------------------------

    public function test_me_profile_passthrough(): void
    {
        $user = $this->createUserWithToken();
        $this->actingAs($user, 'web');

        $baseUrl = rtrim((string) config('identity.profile_api_base_url'), '/');

        Http::fake([
            $baseUrl.'/me/profile' => Http::response([
                'sub' => 'sg_me_test_01',
                'full_name' => 'Me User',
                'bio' => 'A test user',
            ]),
        ]);

        $this->getJson('/api/v1/me/profile')
            ->assertOk()
            ->assertJsonPath('sub', 'sg_me_test_01')
            ->assertJsonPath('full_name', 'Me User');
    }

    // ---------------------------------------------------------------------------
    // GET /api/v1/me/role-profiles — passthrough to role profiles API
    // ---------------------------------------------------------------------------

    public function test_me_role_profiles_passthrough(): void
    {
        $user = $this->createUserWithToken();
        $this->actingAs($user, 'web');

        $baseUrl = rtrim((string) config('identity.profile_api_base_url'), '/');

        Http::fake([
            $baseUrl.'/me/role-profiles' => Http::response([
                ['role' => 'founder', 'organization_id' => 'org_01'],
            ]),
        ]);

        $this->getJson('/api/v1/me/role-profiles')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.role', 'founder');
    }

    // ---------------------------------------------------------------------------
    // GET /api/v1/me/startups — passthrough to startups API
    // ---------------------------------------------------------------------------

    public function test_me_startups_passthrough(): void
    {
        $user = $this->createUserWithToken();
        $this->actingAs($user, 'web');

        $baseUrl = rtrim((string) config('identity.profile_api_base_url'), '/');

        Http::fake([
            $baseUrl.'/me/startups' => Http::response([
                ['id' => 'startup_01', 'name' => 'Acme Inc', 'role' => 'co-founder'],
            ]),
        ]);

        $this->getJson('/api/v1/me/startups')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Acme Inc');
    }

    // ---------------------------------------------------------------------------
    // 401 when user has no stored token
    // ---------------------------------------------------------------------------

    public function test_me_profile_without_stored_token_returns_401(): void
    {
        $user = Account::create([
            'email' => 'notoken@example.com',
            'display_name' => 'No Token User',
        ]);

        LinkedIdentity::create([
            'account_id' => $user->id,
            'provider' => 'startup_gate',
            'subject_id' => 'sg_no_token',
            'linked_at' => now(),
        ]);

        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/me/profile')->assertUnauthorized();
    }

    public function test_me_role_profiles_without_stored_token_returns_401(): void
    {
        $user = Account::create([
            'email' => 'notoken2@example.com',
            'display_name' => 'No Token User 2',
        ]);

        LinkedIdentity::create([
            'account_id' => $user->id,
            'provider' => 'startup_gate',
            'subject_id' => 'sg_no_token_2',
            'linked_at' => now(),
        ]);

        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/me/role-profiles')->assertUnauthorized();
    }

    public function test_me_startups_without_stored_token_returns_401(): void
    {
        $user = Account::create([
            'email' => 'notoken3@example.com',
            'display_name' => 'No Token User 3',
        ]);

        LinkedIdentity::create([
            'account_id' => $user->id,
            'provider' => 'startup_gate',
            'subject_id' => 'sg_no_token_3',
            'linked_at' => now(),
        ]);

        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/me/startups')->assertUnauthorized();
    }
}
