<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define the gate for testing
        Gate::define('viewWebhookDashboard', function ($user = null) {
            return true;
        });
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Use web middleware without auth for testing
        $app['config']->set('webhooks.dashboard.middleware', ['web']);
    }

    public function test_events_index_returns_200(): void
    {
        $response = $this->get(route('webhooker.events.index'));
        $response->assertStatus(200);
        $response->assertViewIs('webhooker::events.index');
    }

    public function test_events_index_shows_events(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Endpoint',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['order_id' => 123],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        $response = $this->get(route('webhooker.events.index'));
        $response->assertStatus(200);
        $response->assertSee('order.created');
    }

    public function test_event_detail_shows_event(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'payment.received',
            'payload' => ['amount' => 99.99],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        $response = $this->get(route('webhooker.events.show', $event->id));
        $response->assertStatus(200);
        $response->assertSee('payment.received');
    }

    public function test_event_detail_returns_404_for_missing_event(): void
    {
        $response = $this->get(route('webhooker.events.show', 99999));
        $response->assertStatus(404);
    }

    public function test_endpoints_index_returns_200(): void
    {
        $response = $this->get(route('webhooker.endpoints.index'));
        $response->assertStatus(200);
        $response->assertViewIs('webhooker::endpoints.index');
    }

    public function test_endpoints_index_shows_endpoints(): void
    {
        WebhookEndpoint::create([
            'name' => 'My Webhook',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $response = $this->get(route('webhooker.endpoints.index'));
        $response->assertStatus(200);
        $response->assertSee('My Webhook');
    }

    public function test_events_filtered_by_status(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'delivered.event',
            'payload' => [],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'failed.event',
            'payload' => [],
            'status' => 'failed',
            'attempts_count' => 5,
        ]);

        $response = $this->get(route('webhooker.events.index', ['status' => 'failed']));
        $response->assertStatus(200);
        $response->assertSee('failed.event');
        $response->assertDontSee('delivered.event');
    }

    public function test_dashboard_gate_protects_routes(): void
    {
        Gate::define('viewWebhookDashboard', function ($user = null) {
            return false;
        });

        $response = $this->get(route('webhooker.events.index'));
        $response->assertStatus(403);
    }
}
