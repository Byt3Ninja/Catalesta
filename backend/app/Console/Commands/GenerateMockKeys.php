<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Dev-helper command that generates a fresh RSA-2048 keypair and prints
 * the env lines needed to configure the Startup Gate mock container.
 *
 * Usage:
 *   php artisan sg-mock:keys
 *
 * The PEMs are base64-encoded so they fit on a single env line.
 * MockKeys::decodePem() automatically detects and decodes base64 values.
 *
 * Copy the output into .env or your secrets manager:
 *   SG_MOCK_PRIVATE_KEY=<base64-encoded PEM>
 *   SG_MOCK_PUBLIC_KEY=<base64-encoded PEM>
 */
final class GenerateMockKeys extends Command
{
    protected $signature = 'sg-mock:keys';

    protected $description = 'Generate a fresh RSA-2048 keypair for the Startup Gate mock (dev helper — prints env lines)';

    public function handle(): int
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            $this->error('openssl_pkey_new() failed — ensure PHP OpenSSL extension is loaded.');

            return self::FAILURE;
        }

        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        $publicPem = $details['key'] ?? null;

        if (! $privatePem || ! $publicPem) {
            throw new RuntimeException('Failed to export generated RSA keypair.');
        }

        $privateB64 = base64_encode($privatePem);
        $publicB64 = base64_encode($publicPem);

        $this->info('# Add these lines to your .env file (mock role only):');
        $this->line('');
        $this->line("SG_MOCK_PRIVATE_KEY={$privateB64}");
        $this->line("SG_MOCK_PUBLIC_KEY={$publicB64}");
        $this->line('');
        $this->comment('# Note: values are base64-encoded PEMs. MockKeys decodes them automatically.');

        return self::SUCCESS;
    }
}
