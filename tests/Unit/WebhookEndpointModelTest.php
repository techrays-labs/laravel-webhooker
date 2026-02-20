<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Unit;

use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;

class WebhookEndpointModelTest extends TestCase
{
    public function test_is_outbound(): void
    {
        $endpoint = new WebhookEndpoint(['direction' => 'outbound']);
        $this->assertTrue($endpoint->isOutbound());
        $this->assertFalse($endpoint->isInbound());
    }

    public function test_is_inbound(): void
    {
        $endpoint = new WebhookEndpoint(['direction' => 'inbound']);
        $this->assertTrue($endpoint->isInbound());
        $this->assertFalse($endpoint->isOutbound());
    }

    public function test_secret_is_hidden_from_serialization(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'super-secret-key',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $array = $endpoint->toArray();
        $this->assertArrayNotHasKey('secret', $array);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => 1,
            'timeout_seconds' => 30,
        ]);

        $this->assertIsBool($endpoint->is_active);
        $this->assertTrue($endpoint->is_active);
    }
}
