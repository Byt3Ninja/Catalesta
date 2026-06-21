<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Identity\Http\Resources\AccountSessionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NativeAuthFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_table_has_native_auth_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('accounts', 'password'));
        $this->assertTrue(Schema::hasColumn('accounts', 'email_verified_at'));
    }

    public function test_password_is_hashed_on_set(): void
    {
        $a = Account::create(['email' => 'h@example.com', 'password' => 'secret-pw']);
        $this->assertNotSame('secret-pw', $a->password);
        $this->assertTrue(password_verify('secret-pw', $a->password));
    }

    public function test_make_account_is_verified_and_session_resource_reports_it(): void
    {
        $a = $this->makeAccount();
        $this->assertTrue($a->fresh()->hasVerifiedEmail());

        $arr = (new AccountSessionResource($a))
            ->toArray(request());

        $this->assertTrue($arr['email_verified']);
        $this->assertSame(['startup_gate'], $arr['linked_providers']);
        $this->assertFalse($arr['has_password']);
        $this->assertArrayHasKey('startup_gate_subject_id', $arr);
    }
}
