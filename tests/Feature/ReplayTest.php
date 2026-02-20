<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use TechRaysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechRaysLabs\Webhooker\Jobs\ProcessInboundWebhookJob;
use TechRaysLabs\Webhooker\Models\WebhookEndpoint;
use TechRaysLabs\Webhooker\Models\WebhookEvent;
use TechRaysLabs\Webhooker\Tests\TestCase;

class ReplayTest extends TestCase
{
    public function test_replay_outbound_event(): void
    {
        Queue::fake();

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
            'event_name' => 'order.created',
            'payload' => ['order_id' => 123],
            'status' => WebhookEvent::STATUS_FAILED,
            'attempts_count' => 5,
        ]);

        $this->artisan('webhook:replay', ['event_id' => $event->id])
            ->expectsOutputToContain('has been queued for replay')
            ->assertSuccessful();

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        $this->assertNull($event->next_retry_at);

        Queue::assertPushed(DispatchWebhookJob::class, function ($job) use ($event) {
            return $job->eventId === $event->id;
        });
    }

    public function test_replay_inbound_event(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Inbound',
            'url' => 'https://example.com',
            'direction' => 'inbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'payment.failed',
            'payload' => ['payment_id' => 456],
            'status' => WebhookEvent::STATUS_FAILED,
            'attempts_count' => 1,
        ]);

        $this->artisan('webhook:replay', ['event_id' => $event->id])
            ->expectsOutputToContain('has been queued for reprocessing')
            ->assertSuccessful();

        Queue::assertPushed(ProcessInboundWebhookJob::class, function ($job) use ($event) {
            return $job->eventId === $event->id;
        });
    }

    public function test_replay_nonexistent_event_fails(): void
    {
        $this->artisan('webhook:replay', ['event_id' => 99999])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }
}
