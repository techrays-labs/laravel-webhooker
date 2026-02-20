<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;

class EndpointHealthCommandTest extends TestCase
{
    public function test_health_command_shows_endpoint_status(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test API',
            'url' => 'https://example.com',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        for ($i = 0; $i < 5; $i++) {
            WebhookEvent::create([
                'endpoint_id' => $endpoint->id,
                'event_name' => 'test.event',
                'payload' => [],
                'status' => 'delivered',
                'attempts_count' => 1,
            ]);
        }

        $this->artisan('webhook:health')
            ->expectsTable(
                ['Token', 'Name', 'Direction', 'Success Rate', 'Avg Response', 'Status'],
                [
                    [$endpoint->route_token, 'Test API', 'outbound', '100%', '0ms', 'healthy'],
                ]
            )
            ->assertSuccessful();
    }

    public function test_health_command_shows_message_when_no_endpoints(): void
    {
        $this->artisan('webhook:health')
            ->expectsOutputToContain('No active endpoints found')
            ->assertSuccessful();
    }
}
