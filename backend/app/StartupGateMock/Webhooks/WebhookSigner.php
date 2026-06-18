<?php

declare(strict_types=1);

namespace App\StartupGateMock\Webhooks;

final class WebhookSigner
{
    public function sign(string $body): string
    {
        $secret = (string) config('identity.mock.webhook_secret');
        $hmac = hash_hmac('sha256', $body, $secret);

        return 'sha256='.$hmac;
    }

    public function verify(string $body, string $signature): bool
    {
        $expectedSignature = $this->sign($body);

        return hash_equals($expectedSignature, $signature);
    }
}
