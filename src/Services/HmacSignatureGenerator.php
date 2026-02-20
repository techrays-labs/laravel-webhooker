<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use TechraysLabs\Webhooker\Contracts\SignatureGenerator;

/**
 * HMAC-based signature generator for webhook payloads.
 */
class HmacSignatureGenerator implements SignatureGenerator
{
    private readonly string $algorithm;

    public function __construct()
    {
        $this->algorithm = config('webhooks.signing_algorithm', 'sha256');
    }

    public function generate(string $payload, string $secret): string
    {
        return hash_hmac($this->algorithm, $payload, $secret);
    }

    public function verify(string $payload, string $secret, string $signature): bool
    {
        $expected = $this->generate($payload, $secret);

        return hash_equals($expected, $signature);
    }
}
