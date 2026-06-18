<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\StartupGateMock\Webhooks\WebhookPayloadFactory;
use App\StartupGateMock\Webhooks\WebhookSigner;
use Tests\TestCase;

class WebhookPayloadContractTest extends TestCase
{
    private WebhookPayloadFactory $factory;

    private WebhookSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new WebhookPayloadFactory;
        $this->signer = new WebhookSigner;
    }

    public function test_profile_updated_payload_structure(): void
    {
        $data = ['user_id' => 'user-123', 'name' => 'John Doe'];
        $payload = $this->factory->profileUpdated($data);

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('version', $payload);
        $this->assertArrayHasKey('occurred_at', $payload);
        $this->assertArrayHasKey('data', $payload);

        $this->assertIsString($payload['id']);
        $this->assertSame('ProfileUpdated', $payload['type']);
        $this->assertIsInt($payload['version']);
        $this->assertIsString($payload['occurred_at']);
        $this->assertSame($data, $payload['data']);
    }

    public function test_consent_revoked_payload_structure(): void
    {
        $data = ['user_id' => 'user-456', 'scope' => 'email'];
        $payload = $this->factory->consentRevoked($data);

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('version', $payload);
        $this->assertArrayHasKey('occurred_at', $payload);
        $this->assertArrayHasKey('data', $payload);

        $this->assertIsString($payload['id']);
        $this->assertSame('ConsentRevoked', $payload['type']);
        $this->assertIsInt($payload['version']);
        $this->assertIsString($payload['occurred_at']);
        $this->assertSame($data, $payload['data']);
    }

    public function test_role_profile_approved_payload_structure(): void
    {
        $data = ['user_id' => 'user-789', 'role' => 'mentor'];
        $payload = $this->factory->roleProfileApproved($data);

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('version', $payload);
        $this->assertArrayHasKey('occurred_at', $payload);
        $this->assertArrayHasKey('data', $payload);

        $this->assertIsString($payload['id']);
        $this->assertSame('RoleProfileApproved', $payload['type']);
        $this->assertIsInt($payload['version']);
        $this->assertIsString($payload['occurred_at']);
        $this->assertSame($data, $payload['data']);
    }

    public function test_achievement_published_payload_structure(): void
    {
        $data = ['user_id' => 'user-012', 'achievement' => 'completed_course'];
        $payload = $this->factory->achievementPublished($data);

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('version', $payload);
        $this->assertArrayHasKey('occurred_at', $payload);
        $this->assertArrayHasKey('data', $payload);

        $this->assertIsString($payload['id']);
        $this->assertSame('AchievementPublished', $payload['type']);
        $this->assertIsInt($payload['version']);
        $this->assertIsString($payload['occurred_at']);
        $this->assertSame($data, $payload['data']);
    }

    public function test_signature_verification(): void
    {
        $payload = $this->factory->profileUpdated(['user_id' => 'test-user']);
        $json = json_encode($payload);

        $signature = $this->signer->sign($json);

        $this->assertTrue($this->signer->verify($json, $signature));
    }

    public function test_signature_has_prefix(): void
    {
        $payload = $this->factory->profileUpdated(['user_id' => 'test-user']);
        $json = json_encode($payload);

        $signature = $this->signer->sign($json);

        $this->assertStringStartsWith('sha256=', $signature);
    }

    public function test_tampered_body_fails_verification(): void
    {
        $payload = $this->factory->profileUpdated(['user_id' => 'test-user']);
        $json = json_encode($payload);

        $signature = $this->signer->sign($json);

        $tamperedJson = json_encode(['tampered' => true]);

        $this->assertFalse($this->signer->verify($tamperedJson, $signature));
    }

    public function test_signature_equals_independently_computed_hmac(): void
    {
        $payload = $this->factory->profileUpdated(['user_id' => 'test-user']);
        $json = json_encode($payload);

        $signature = $this->signer->sign($json);
        $secret = (string) config('identity.mock.webhook_secret');
        $expectedHmac = 'sha256='.hash_hmac('sha256', $json, $secret);

        $this->assertSame($expectedHmac, $signature);
    }
}
