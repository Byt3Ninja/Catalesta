<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Domain\Models\ExternalUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExternalUserProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_is_keyed_on_sub_not_email(): void
    {
        $claims = ['sub' => 'sg_user_01', 'email' => 'a@example.com', 'name' => 'A', 'locale' => 'en', 'profile_updated_at' => 1781712000];
        $u1 = ExternalUser::projectFromClaims($claims);

        // same sub, new email -> SAME projection updated, not a new row
        $u2 = ExternalUser::projectFromClaims([...$claims, 'email' => 'changed@example.com']);

        $this->assertSame($u1->id, $u2->id);
        $this->assertSame('changed@example.com', $u2->email);
        $this->assertDatabaseCount('external_users', 1);
    }

    public function test_different_sub_creates_distinct_user_even_with_same_email(): void
    {
        ExternalUser::projectFromClaims(['sub' => 'sg_user_01', 'email' => 'same@example.com']);
        ExternalUser::projectFromClaims(['sub' => 'sg_user_02', 'email' => 'same@example.com']);
        $this->assertDatabaseCount('external_users', 2);
    }
}
