<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Organizations\Domain\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_organization_with_name_persists_with_ulid_and_slug(): void
    {
        $org = Organization::create([
            'name' => 'Acme Corporation',
        ]);

        $this->assertTrue(strlen($org->id) === 26); // ULID length
        $this->assertSame('acme-corporation', $org->slug);
        $this->assertDatabaseCount('organizations', 1);

        $retrieved = Organization::find($org->id);
        $this->assertNotNull($retrieved);
        $this->assertSame('Acme Corporation', $retrieved->name);
        $this->assertSame('acme-corporation', $retrieved->slug);
    }

    public function test_slug_is_auto_derived_from_name_if_not_provided(): void
    {
        $org = Organization::create([
            'name' => 'Test Company Inc',
        ]);

        $this->assertSame('test-company-inc', $org->slug);
    }

    public function test_branding_casts_to_array(): void
    {
        $brandingData = [
            'primary_color' => '#000000',
            'logo_url' => 'https://example.com/logo.png',
        ];

        $org = Organization::create([
            'name' => 'Branded Corp',
            'branding' => $brandingData,
        ]);

        $this->assertIsArray($org->branding);
        $this->assertSame('#000000', $org->branding['primary_color']);
        $this->assertSame('https://example.com/logo.png', $org->branding['logo_url']);
    }

    public function test_branding_nullable(): void
    {
        $org = Organization::create([
            'name' => 'No Branding Org',
        ]);

        $this->assertNull($org->branding);
    }
}
