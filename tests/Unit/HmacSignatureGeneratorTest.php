<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Tests\Unit;

use TechRaysLabs\Webhooker\Services\HmacSignatureGenerator;
use TechRaysLabs\Webhooker\Tests\TestCase;

class HmacSignatureGeneratorTest extends TestCase
{
    private HmacSignatureGenerator $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('webhooks.signing_algorithm', 'sha256');
        $this->signer = new HmacSignatureGenerator;
    }

    public function test_generate_produces_valid_hmac(): void
    {
        $payload = '{"event":"test"}';
        $secret = 'my-secret-key';

        $signature = $this->signer->generate($payload, $secret);

        $expected = hash_hmac('sha256', $payload, $secret);
        $this->assertEquals($expected, $signature);
    }

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $payload = '{"event":"test"}';
        $secret = 'my-secret-key';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($this->signer->verify($payload, $secret, $signature));
    }

    public function test_verify_returns_false_for_invalid_signature(): void
    {
        $payload = '{"event":"test"}';
        $secret = 'my-secret-key';

        $this->assertFalse($this->signer->verify($payload, $secret, 'invalid-signature'));
    }

    public function test_verify_returns_false_for_wrong_secret(): void
    {
        $payload = '{"event":"test"}';
        $signature = hash_hmac('sha256', $payload, 'correct-secret');

        $this->assertFalse($this->signer->verify($payload, 'wrong-secret', $signature));
    }

    public function test_verify_returns_false_for_tampered_payload(): void
    {
        $secret = 'my-secret-key';
        $signature = hash_hmac('sha256', '{"event":"original"}', $secret);

        $this->assertFalse($this->signer->verify('{"event":"tampered"}', $secret, $signature));
    }

    public function test_different_payloads_produce_different_signatures(): void
    {
        $secret = 'my-secret-key';

        $sig1 = $this->signer->generate('payload-1', $secret);
        $sig2 = $this->signer->generate('payload-2', $secret);

        $this->assertNotEquals($sig1, $sig2);
    }
}
