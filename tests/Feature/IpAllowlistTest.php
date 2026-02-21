<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;

class IpAllowlistTest extends TestCase
{
    public function test_ip_allowlist_disabled_by_default(): void
    {
        $this->assertFalse(config('webhooks.inbound.ip_allowlist.enabled'));
    }

    public function test_request_passes_when_allowlist_disabled(): void
    {
        config(['webhooks.inbound.ip_allowlist.enabled' => false]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Inbound',
            'url' => 'https://example.com/inbound',
            'direction' => 'inbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $signer = app(SignatureGenerator::class);
        $payload = '{"event":"test"}';
        $signature = $signer->generate($payload, 'test-secret');
        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');

        $response = $this->postJson(
            "/api/webhooks/inbound/{$endpoint->route_token}",
            json_decode($payload, true),
            [$signatureHeader => $signature]
        );

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_request_blocked_by_global_allowlist(): void
    {
        config([
            'webhooks.inbound.ip_allowlist.enabled' => true,
            'webhooks.inbound.ip_allowlist.global' => ['192.168.1.1'],
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Inbound',
            'url' => 'https://example.com/inbound',
            'direction' => 'inbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $response = $this->postJson(
            "/api/webhooks/inbound/{$endpoint->route_token}",
            ['event' => 'test'],
            ['X-Webhook-Signature' => 'invalid']
        );

        $response->assertStatus(403);
    }

    public function test_request_allowed_by_matching_ip(): void
    {
        config([
            'webhooks.inbound.ip_allowlist.enabled' => true,
            'webhooks.inbound.ip_allowlist.global' => ['127.0.0.1'],
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Inbound',
            'url' => 'https://example.com/inbound',
            'direction' => 'inbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $signer = app(SignatureGenerator::class);
        $payload = '{"event":"test"}';
        $signature = $signer->generate($payload, 'test-secret');
        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');

        $response = $this->postJson(
            "/api/webhooks/inbound/{$endpoint->route_token}",
            json_decode($payload, true),
            [$signatureHeader => $signature]
        );

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_request_allowed_by_endpoint_specific_allowlist(): void
    {
        config([
            'webhooks.inbound.ip_allowlist.enabled' => true,
            'webhooks.inbound.ip_allowlist.global' => [],
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Inbound',
            'url' => 'https://example.com/inbound',
            'direction' => 'inbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
            'allowed_ips' => ['127.0.0.1'],
        ]);

        $signer = app(SignatureGenerator::class);
        $payload = '{"event":"test"}';
        $signature = $signer->generate($payload, 'test-secret');
        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');

        $response = $this->postJson(
            "/api/webhooks/inbound/{$endpoint->route_token}",
            json_decode($payload, true),
            [$signatureHeader => $signature]
        );

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_empty_allowlist_allows_all(): void
    {
        config([
            'webhooks.inbound.ip_allowlist.enabled' => true,
            'webhooks.inbound.ip_allowlist.global' => [],
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Inbound',
            'url' => 'https://example.com/inbound',
            'direction' => 'inbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
            'allowed_ips' => [],
        ]);

        $signer = app(SignatureGenerator::class);
        $payload = '{"event":"test"}';
        $signature = $signer->generate($payload, 'test-secret');
        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');

        $response = $this->postJson(
            "/api/webhooks/inbound/{$endpoint->route_token}",
            json_decode($payload, true),
            [$signatureHeader => $signature]
        );

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
