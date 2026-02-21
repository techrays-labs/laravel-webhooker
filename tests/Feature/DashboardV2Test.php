<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookAttempt;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;

class DashboardV2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewWebhookDashboard', function ($user = null) {
            return true;
        });
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('webhooks.dashboard.middleware', ['web']);
    }

    public function test_events_index_shows_stats_panel(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        $response = $this->get(route('webhooker.events.index'));
        $response->assertStatus(200);
        $response->assertSee('Events (24h)');
        $response->assertSee('Success Rate');
    }

    public function test_event_show_displays_timeline(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        WebhookAttempt::create([
            'event_id' => $event->id,
            'response_status' => 200,
            'response_body' => 'OK',
            'duration_ms' => 150,
            'attempted_at' => now(),
        ]);

        $response = $this->get(route('webhooker.events.show', $event->id));
        $response->assertStatus(200);
        $response->assertSee('Delivery Timeline');
        $response->assertSee('HTTP 200');
    }

    public function test_event_show_displays_replay_button_for_failed(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => 'failed',
            'attempts_count' => 3,
        ]);

        $response = $this->get(route('webhooker.events.show', $event->id));
        $response->assertStatus(200);
        $response->assertSee('Replay Event');
    }

    public function test_endpoint_detail_page_loads(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Payment Hook',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $response = $this->get(route('webhooker.endpoints.show', $endpoint->id));
        $response->assertStatus(200);
        $response->assertSee('Payment Hook');
        $response->assertSee($endpoint->route_token);
    }

    public function test_endpoint_detail_shows_health_status(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Health Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $response = $this->get(route('webhooker.endpoints.show', $endpoint->id));
        $response->assertStatus(200);
        $response->assertSee('Health Status');
    }

    public function test_endpoint_detail_returns_404_for_missing(): void
    {
        $response = $this->get(route('webhooker.endpoints.show', 99999));
        $response->assertStatus(404);
    }

    public function test_bulk_replay_processes_selected_events(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event1 = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => 'failed',
            'attempts_count' => 3,
        ]);

        $event2 = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.updated',
            'payload' => ['id' => 2],
            'status' => 'failed',
            'attempts_count' => 3,
        ]);

        $response = $this->post(route('webhooker.events.bulk'), [
            '_token' => csrf_token(),
            'action' => 'replay',
            'event_ids' => [$event1->id, $event2->id],
        ]);

        $response->assertRedirect(route('webhooker.events.index'));

        $event1->refresh();
        $event2->refresh();
        $this->assertEquals('pending', $event1->status);
        $this->assertEquals('pending', $event2->status);

        Queue::assertPushed(DispatchWebhookJob::class, 2);
    }

    public function test_bulk_delete_removes_selected_events(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => 'failed',
            'attempts_count' => 3,
        ]);

        $response = $this->post(route('webhooker.events.bulk'), [
            '_token' => csrf_token(),
            'action' => 'delete',
            'event_ids' => [$event->id],
        ]);

        $response->assertRedirect(route('webhooker.events.index'));
        $this->assertDatabaseMissing('webhook_events', ['id' => $event->id]);
    }

    public function test_bulk_action_with_no_events_returns_error(): void
    {
        $response = $this->post(route('webhooker.events.bulk'), [
            '_token' => csrf_token(),
            'action' => 'replay',
            'event_ids' => [],
        ]);

        $response->assertRedirect(route('webhooker.events.index'));
        $response->assertSessionHas('error');
    }

    public function test_bulk_replay_cli_with_status_filter(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.failed',
            'payload' => ['id' => 1],
            'status' => 'failed',
            'attempts_count' => 5,
        ]);

        WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.delivered',
            'payload' => ['id' => 2],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        $this->artisan('webhook:replay', ['--status' => 'failed'])
            ->expectsOutputToContain('1 event(s) queued for replay')
            ->assertSuccessful();

        Queue::assertPushed(DispatchWebhookJob::class, 1);
    }

    public function test_bulk_replay_cli_with_endpoint_filter(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => 'failed',
            'attempts_count' => 5,
        ]);

        $this->artisan('webhook:replay', ['--endpoint' => $endpoint->route_token, '--status' => 'failed'])
            ->expectsOutputToContain('1 event(s) queued for replay')
            ->assertSuccessful();
    }

    public function test_dark_mode_toggle_exists_in_layout(): void
    {
        $response = $this->get(route('webhooker.events.index'));
        $response->assertStatus(200);
        $response->assertSee('theme-toggle');
        $response->assertSee('toggleTheme');
    }

    public function test_endpoints_index_links_to_detail_page(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Linked Endpoint',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $response = $this->get(route('webhooker.endpoints.index'));
        $response->assertStatus(200);
        $response->assertSee('Details');
    }
}
