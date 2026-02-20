<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Carbon;
use TechRaysLabs\Webhooker\Models\WebhookEndpoint;
use TechRaysLabs\Webhooker\Models\WebhookEvent;
use TechRaysLabs\Webhooker\Tests\TestCase;

class PruneTest extends TestCase
{
    public function test_prune_deletes_old_events(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        // Old event (40 days ago)
        $oldEvent = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'old.event',
            'payload' => ['old' => true],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);
        $oldEvent->created_at = Carbon::now()->subDays(40);
        $oldEvent->save();

        // Recent event (5 days ago)
        $recentEvent = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'recent.event',
            'payload' => ['recent' => true],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);
        $recentEvent->created_at = Carbon::now()->subDays(5);
        $recentEvent->save();

        $this->artisan('webhook:prune')
            ->expectsOutputToContain('Pruned 1 webhook event(s) older than 30 day(s)')
            ->assertSuccessful();

        $this->assertDatabaseMissing('webhook_events', ['event_name' => 'old.event']);
        $this->assertDatabaseHas('webhook_events', ['event_name' => 'recent.event']);
    }

    public function test_prune_with_custom_days_option(): void
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
            'event_name' => 'event.10days',
            'payload' => [],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);
        $event->created_at = Carbon::now()->subDays(10);
        $event->save();

        $this->artisan('webhook:prune', ['--days' => 7])
            ->expectsOutputToContain('Pruned 1 webhook event(s) older than 7 day(s)')
            ->assertSuccessful();
    }

    public function test_prune_with_no_old_events(): void
    {
        $this->artisan('webhook:prune')
            ->expectsOutputToContain('Pruned 0 webhook event(s)')
            ->assertSuccessful();
    }
}
