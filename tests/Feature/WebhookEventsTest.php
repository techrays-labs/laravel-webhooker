<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use TechraysLabs\Webhooker\Events\InboundWebhookFailed;
use TechraysLabs\Webhooker\Events\InboundWebhookProcessed;
use TechraysLabs\Webhooker\Events\InboundWebhookReceived;
use TechraysLabs\Webhooker\Events\WebhookFailed;
use TechraysLabs\Webhooker\Events\WebhookReplayRequested;
use TechraysLabs\Webhooker\Events\WebhookRetriesExhausted;
use TechraysLabs\Webhooker\Events\WebhookSending;
use TechraysLabs\Webhooker\Events\WebhookSent;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Jobs\ProcessInboundWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;

class WebhookEventsTest extends TestCase
{
    public function test_webhook_sending_event_fires_before_delivery(): void
    {
        Event::fake([WebhookSending::class, WebhookSent::class]);
        Http::fake(['*' => Http::response('OK', 200)]);

        $endpoint = $this->createOutboundEndpoint();
        $event = $this->createPendingEvent($endpoint);

        $this->dispatchJob($event);

        Event::assertDispatched(WebhookSending::class, function ($e) use ($event, $endpoint) {
            return $e->webhookEvent->id === $event->id
                && $e->endpoint->id === $endpoint->id;
        });
    }

    public function test_webhook_sent_event_fires_on_success(): void
    {
        Event::fake([WebhookSending::class, WebhookSent::class]);
        Http::fake(['*' => Http::response('OK', 200)]);

        $endpoint = $this->createOutboundEndpoint();
        $event = $this->createPendingEvent($endpoint);

        $this->dispatchJob($event);

        Event::assertDispatched(WebhookSent::class, function ($e) use ($event, $endpoint) {
            return $e->webhookEvent->id === $event->id
                && $e->endpoint->id === $endpoint->id
                && $e->attempt->response_status === 200;
        });
    }

    public function test_webhook_failed_event_fires_on_failure(): void
    {
        Event::fake([WebhookSending::class, WebhookFailed::class]);
        Http::fake(['*' => Http::response('Error', 500)]);
        Queue::fake();

        $endpoint = $this->createOutboundEndpoint();
        $event = $this->createPendingEvent($endpoint);

        $this->dispatchJob($event);

        Event::assertDispatched(WebhookFailed::class, function ($e) use ($event, $endpoint) {
            return $e->webhookEvent->id === $event->id
                && $e->endpoint->id === $endpoint->id;
        });
    }

    public function test_webhook_retries_exhausted_event_fires_when_max_reached(): void
    {
        Event::fake([WebhookSending::class, WebhookFailed::class, WebhookRetriesExhausted::class]);
        Http::fake(['*' => Http::response('Error', 500)]);
        Queue::fake();

        $endpoint = $this->createOutboundEndpoint();
        $event = $this->createPendingEvent($endpoint, 4); // At max - 1

        $this->dispatchJob($event);

        Event::assertDispatched(WebhookRetriesExhausted::class, function ($e) use ($event, $endpoint) {
            return $e->webhookEvent->id === $event->id
                && $e->endpoint->id === $endpoint->id;
        });
    }

    public function test_webhook_replay_requested_event_fires(): void
    {
        Event::fake([WebhookReplayRequested::class]);
        Queue::fake();

        $endpoint = $this->createOutboundEndpoint();
        $event = $this->createPendingEvent($endpoint);
        $event->update(['status' => WebhookEvent::STATUS_FAILED]);

        $this->artisan('webhook:replay', ['event_id' => $event->id])
            ->assertSuccessful();

        Event::assertDispatched(WebhookReplayRequested::class, function ($e) use ($event) {
            return $e->webhookEvent->id === $event->id;
        });
    }

    public function test_inbound_webhook_received_event_fires(): void
    {
        Event::fake([InboundWebhookReceived::class]);
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Inbound',
            'url' => 'https://example.com',
            'direction' => 'inbound',
            'secret' => 'inbound-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $payload = json_encode(['data' => 'test']);
        $signature = hash_hmac('sha256', $payload, 'inbound-secret');

        $this->postJson(
            '/api/webhooks/inbound/'.$endpoint->route_token,
            json_decode($payload, true),
            ['X-Webhook-Signature' => $signature],
        );

        Event::assertDispatched(InboundWebhookReceived::class, function ($e) use ($endpoint) {
            return $e->endpoint->id === $endpoint->id;
        });
    }

    public function test_inbound_webhook_processed_event_fires_on_success(): void
    {
        Event::fake([InboundWebhookProcessed::class]);

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
            'event_name' => 'test.event',
            'payload' => ['key' => 'value'],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $job = new ProcessInboundWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\InboundProcessor::class),
        );

        Event::assertDispatched(InboundWebhookProcessed::class, function ($e) use ($event) {
            return $e->webhookEvent->id === $event->id;
        });
    }

    public function test_inbound_webhook_failed_event_fires_on_failure(): void
    {
        Event::fake([InboundWebhookFailed::class]);

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
            'event_name' => 'test.event',
            'payload' => ['key' => 'value'],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        // Use a processor that throws
        $processor = new class implements \TechraysLabs\Webhooker\Contracts\InboundProcessor
        {
            public function process(\TechraysLabs\Webhooker\Models\WebhookEvent $event): bool
            {
                throw new \RuntimeException('Processing failed');
            }
        };

        $job = new ProcessInboundWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            $processor,
        );

        Event::assertDispatched(InboundWebhookFailed::class, function ($e) use ($event) {
            return $e->webhookEvent->id === $event->id
                && $e->exception instanceof \RuntimeException;
        });
    }

    private function createOutboundEndpoint(): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    private function createPendingEvent(WebhookEndpoint $endpoint, int $attempts = 0): WebhookEvent
    {
        return WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['order_id' => 123],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => $attempts,
        ]);
    }

    private function dispatchJob(WebhookEvent $event): void
    {
        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\SignatureGenerator::class),
            app(\TechraysLabs\Webhooker\Contracts\RetryStrategy::class),
        );
    }
}
