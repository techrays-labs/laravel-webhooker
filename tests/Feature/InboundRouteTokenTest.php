<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;

class InboundRouteTokenTest extends TestCase
{
    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Token Test',
            'url' => 'https://example.com',
            'direction' => 'inbound',
            'secret' => 'token-test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_inbound_route_resolves_by_token(): void
    {
        $payload = json_encode(['test' => true]);
        $signature = hash_hmac('sha256', $payload, 'token-test-secret');

        $response = $this->postJson(
            '/api/webhooks/inbound/'.$this->endpoint->route_token,
            json_decode($payload, true),
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(202);
    }

    public function test_unknown_token_returns_404(): void
    {
        $payload = json_encode(['test' => true]);
        $signature = hash_hmac('sha256', $payload, 'token-test-secret');

        $response = $this->postJson(
            '/api/webhooks/inbound/ep_doesnotexist',
            json_decode($payload, true),
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(404);
    }

    public function test_integer_id_returns_404(): void
    {
        $payload = json_encode(['test' => true]);
        $signature = hash_hmac('sha256', $payload, 'token-test-secret');

        $response = $this->postJson(
            '/api/webhooks/inbound/'.$this->endpoint->id,
            json_decode($payload, true),
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(404);
    }

    public function test_inactive_endpoint_returns_404(): void
    {
        $this->endpoint->update(['is_active' => false]);

        $payload = json_encode(['test' => true]);
        $signature = hash_hmac('sha256', $payload, 'token-test-secret');

        $response = $this->postJson(
            '/api/webhooks/inbound/'.$this->endpoint->route_token,
            json_decode($payload, true),
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(404);
    }
}
