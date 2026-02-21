<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;

class EndpointListCommandTest extends TestCase
{
    public function test_lists_endpoints(): void
    {
        $ep1 = WebhookEndpoint::create([
            'name' => 'Payments API',
            'url' => 'https://payments.example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'secret-1',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $ep2 = WebhookEndpoint::create([
            'name' => 'Stripe Inbound',
            'url' => 'https://myapp.com/inbound/stripe',
            'direction' => 'inbound',
            'secret' => 'secret-2',
            'is_active' => true,
            'timeout_seconds' => 15,
        ]);

        $this->artisan('webhook:endpoint:list')
            ->expectsTable(
                ['Token', 'Name', 'URL', 'Direction', 'Active', 'Timeout', 'Tags'],
                [
                    [$ep1->route_token, 'Payments API', 'https://payments.example.com/webhook', 'outbound', 'Yes', '30s', ''],
                    [$ep2->route_token, 'Stripe Inbound', 'https://myapp.com/inbound/stripe', 'inbound', 'Yes', '15s', ''],
                ]
            )
            ->assertSuccessful();
    }

    public function test_filters_by_direction(): void
    {
        WebhookEndpoint::create([
            'name' => 'Outbound',
            'url' => 'https://example.com/out',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $inbound = WebhookEndpoint::create([
            'name' => 'Inbound',
            'url' => 'https://example.com/in',
            'direction' => 'inbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->artisan('webhook:endpoint:list', ['--direction' => 'inbound'])
            ->expectsTable(
                ['Token', 'Name', 'URL', 'Direction', 'Active', 'Timeout', 'Tags'],
                [
                    [$inbound->route_token, 'Inbound', 'https://example.com/in', 'inbound', 'Yes', '30s', ''],
                ]
            )
            ->assertSuccessful();
    }

    public function test_shows_message_when_no_endpoints(): void
    {
        $this->artisan('webhook:endpoint:list')
            ->expectsOutputToContain('No endpoints found')
            ->assertSuccessful();
    }
}
