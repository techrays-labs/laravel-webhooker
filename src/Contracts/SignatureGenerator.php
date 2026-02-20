<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Contracts;

/**
 * Contract for generating and verifying HMAC signatures for webhook payloads.
 */
interface SignatureGenerator
{
    /**
     * Generate a signature for the given payload using the provided secret.
     */
    public function generate(string $payload, string $secret): string;

    /**
     * Verify that the given signature matches the expected signature for the payload.
     */
    public function verify(string $payload, string $secret, string $signature): bool;
}
