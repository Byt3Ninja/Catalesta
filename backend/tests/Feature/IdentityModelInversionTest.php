<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Domain\Models\LinkedIdentity;
use App\StartupGateMock\Support\MockKeys;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IdentityModelInversionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Step 1: Schema-shape assertions
    // -------------------------------------------------------------------------

    public function test_target_schema_shape(): void
    {
        $this->assertTrue(Schema::hasTable('accounts'));
        $this->assertTrue(Schema::hasTable('linked_identities'));
        $this->assertTrue(Schema::hasTable('linked_identity_tokens'));
        $this->assertFalse(Schema::hasTable('external_users'));
        $this->assertFalse(Schema::hasTable('external_user_tokens'));

        $this->assertFalse(Schema::hasColumn('accounts', 'startup_gate_subject_id'));
        $this->assertTrue(Schema::hasColumn('linked_identities', 'subject_id'));
        $this->assertTrue(Schema::hasColumn('organization_memberships', 'account_id'));
        $this->assertFalse(Schema::hasColumn('organization_memberships', 'external_user_id'));
        $this->assertTrue(Schema::hasColumn('profile_snapshots', 'account_id'));
        $this->assertFalse(Schema::hasColumn('profile_snapshots', 'external_user_id'));
        $this->assertTrue(Schema::hasColumn('participant_stage_statuses', 'account_id'));
        $this->assertFalse(Schema::hasColumn('participant_stage_statuses', 'external_user_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'actor_account_id'));
    }

    // -------------------------------------------------------------------------
    // Step 1: Projection tests
    // -------------------------------------------------------------------------

    public function test_projection_creates_account_link_and_reuses_on_second_login(): void
    {
        $claims = ['sub' => 'sg_abc', 'email' => 'a@example.com', 'name' => 'A'];

        $link1 = LinkedIdentity::projectFromClaims($claims);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('linked_identities', 1);
        $this->assertSame('sg_abc', $link1->subject_id);

        $link2 = LinkedIdentity::projectFromClaims(['sub' => 'sg_abc', 'email' => 'changed@example.com', 'name' => 'A']);
        $this->assertSame($link1->account_id, $link2->account_id);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertSame('changed@example.com', Account::find($link2->account_id)->email);
    }

    public function test_account_exposes_startup_gate_subject_id(): void
    {
        $account = $this->makeAccount();
        $this->assertNotNull($account->startupGateSubjectId());
        $this->assertStringStartsWith('sub-', $account->startupGateSubjectId());
    }

    // -------------------------------------------------------------------------
    // Step 4: Audit actor assertion — drives full OIDC callback, mirrors AuthFlowTest
    // -------------------------------------------------------------------------

    public function test_login_audit_records_actor_account_id(): void
    {
        // Initiate the OIDC login flow — populates session with state/nonce/verifier
        $login = $this->getJson('/api/v1/auth/login')->assertOk()->json();
        $this->assertArrayHasKey('authorization_url', $login);

        $state = session('oidc.state');
        $nonce = session('oidc.nonce');
        $this->assertNotEmpty($state);
        $this->assertNotEmpty($nonce);

        // Build a valid id_token signed with MockKeys (same as AuthFlowTest)
        $idToken = JWT::encode(
            [
                'iss' => config('identity.oidc.issuer'),
                'aud' => config('identity.oidc.client_id'),
                'sub' => 'sg_audit_user_01',
                'email' => 'audit@example.com',
                'name' => 'Audit User',
                'locale' => 'en',
                'profile_updated_at' => 1781712000,
                'nonce' => $nonce,
                'iat' => time(),
                'exp' => time() + 3600,
            ],
            MockKeys::privateKeyPem(),
            'RS256',
            MockKeys::kid(),
        );

        $issuer = rtrim((string) config('identity.oidc.issuer'), '/');

        Http::fake([
            $issuer.'/oauth/token' => Http::response([
                'id_token' => $idToken,
                'access_token' => 'at',
                'refresh_token' => 'rt',
                'expires_in' => 3600,
                'scope' => 'openid profile.basic.read',
            ]),
            $issuer.'/.well-known/jwks.json' => Http::response(MockKeys::jwks()),
        ]);

        // Complete the OIDC callback — this should create the Account + LinkedIdentity
        // and write an auth.login audit row
        $this->postJson('/api/v1/auth/callback', ['code' => 'abc', 'state' => $state])
            ->assertOk()
            ->assertJsonPath('user.startup_gate_subject_id', 'sg_audit_user_01');

        // Resolve the account that was just created for this sub
        $linkedIdentity = LinkedIdentity::where('provider', 'startup_gate')
            ->where('subject_id', 'sg_audit_user_01')
            ->firstOrFail();

        $accountId = $linkedIdentity->account_id;

        // Assert the audit log recorded actor_account_id correctly
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'actor_account_id' => $accountId,
        ]);
    }
}
