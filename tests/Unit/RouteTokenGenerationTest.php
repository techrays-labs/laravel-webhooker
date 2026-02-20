<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Unit;

use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;

class RouteTokenGenerationTest extends TestCase
{
    public function test_token_is_auto_generated_on_creation(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->assertNotNull($endpoint->route_token);
        $this->assertStringStartsWith('ep_', $endpoint->route_token);
        $this->assertGreaterThanOrEqual(15, strlen($endpoint->route_token));
    }

    public function test_token_is_unique_across_endpoints(): void
    {
        $tokens = [];

        for ($i = 0; $i < 10; $i++) {
            $endpoint = WebhookEndpoint::create([
                'name' => "Endpoint {$i}",
                'url' => 'https://example.com',
                'direction' => 'outbound',
                'secret' => 'secret',
                'is_active' => true,
                'timeout_seconds' => 30,
            ]);
            $tokens[] = $endpoint->route_token;
        }

        $this->assertCount(10, array_unique($tokens));
    }

    public function test_route_key_name_returns_route_token(): void
    {
        $endpoint = new WebhookEndpoint;
        $this->assertEquals('route_token', $endpoint->getRouteKeyName());
    }

    public function test_custom_token_is_preserved(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
            'route_token' => 'ep_customtoken12',
        ]);

        $this->assertEquals('ep_customtoken12', $endpoint->route_token);
    }
}
