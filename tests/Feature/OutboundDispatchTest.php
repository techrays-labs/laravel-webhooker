<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;
use TechraysLabs\Webhooker\Webhooker;

class OutboundDispatchTest extends TestCase
{
    public function test_dispatch_creates_event_and_queues_job(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test Endpoint',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);
        $event = $webhooker->dispatch($endpoint->id, 'order.created', ['order_id' => 123]);

        $this->assertDatabaseHas('webhook_events', [
            'id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'status' => WebhookEvent::STATUS_PENDING,
        ]);

        Queue::assertPushed(DispatchWebhookJob::class, function ($job) use ($event) {
            return $job->eventId === $event->id;
        });
    }

    public function test_broadcast_dispatches_to_all_active_outbound_endpoints(): void
    {
        Queue::fake();

        WebhookEndpoint::create([
            'name' => 'Endpoint 1',
            'url' => 'https://example.com/hook1',
            'direction' => 'outbound',
            'secret' => 'secret-1',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        WebhookEndpoint::create([
            'name' => 'Endpoint 2',
            'url' => 'https://example.com/hook2',
            'direction' => 'outbound',
            'secret' => 'secret-2',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        // Inactive endpoint should be skipped
        WebhookEndpoint::create([
            'name' => 'Inactive',
            'url' => 'https://example.com/hook3',
            'direction' => 'outbound',
            'secret' => 'secret-3',
            'is_active' => false,
            'timeout_seconds' => 30,
        ]);

        // Inbound endpoint should be skipped
        WebhookEndpoint::create([
            'name' => 'Inbound',
            'url' => 'https://example.com/hook4',
            'direction' => 'inbound',
            'secret' => 'secret-4',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);
        $events = $webhooker->broadcast('order.created', ['order_id' => 456]);

        $this->assertCount(2, $events);
        Queue::assertPushed(DispatchWebhookJob::class, 2);
    }

    public function test_successful_delivery_marks_event_as_delivered(): void
    {
        Http::fake([
            'example.com/webhook' => Http::response('OK', 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['order_id' => 123],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\SignatureGenerator::class),
            app(\TechraysLabs\Webhooker\Contracts\RetryStrategy::class),
            app(\TechraysLabs\Webhooker\Contracts\CircuitBreaker::class),
        );

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_DELIVERED, $event->status);
        $this->assertEquals(1, $event->attempts_count);
        $this->assertNotNull($event->last_attempt_at);

        $this->assertDatabaseHas('webhook_attempts', [
            'event_id' => $event->id,
            'response_status' => 200,
        ]);
    }

    public function test_failed_delivery_schedules_retry(): void
    {
        Http::fake([
            'example.com/webhook' => Http::response('Server Error', 500),
        ]);

        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['order_id' => 123],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\SignatureGenerator::class),
            app(\TechraysLabs\Webhooker\Contracts\RetryStrategy::class),
            app(\TechraysLabs\Webhooker\Contracts\CircuitBreaker::class),
        );

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        $this->assertEquals(1, $event->attempts_count);
        $this->assertNotNull($event->next_retry_at);

        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_max_retries_exceeded_marks_event_as_failed(): void
    {
        Http::fake([
            'example.com/webhook' => Http::response('Server Error', 500),
        ]);

        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['order_id' => 123],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 4, // Already at max - 1
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\SignatureGenerator::class),
            app(\TechraysLabs\Webhooker\Contracts\RetryStrategy::class),
            app(\TechraysLabs\Webhooker\Contracts\CircuitBreaker::class),
        );

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_FAILED, $event->status);
        $this->assertEquals(5, $event->attempts_count);
        $this->assertNull($event->next_retry_at);
    }

    public function test_inactive_endpoint_marks_event_as_failed(): void
    {
        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => false,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['order_id' => 123],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\SignatureGenerator::class),
            app(\TechraysLabs\Webhooker\Contracts\RetryStrategy::class),
            app(\TechraysLabs\Webhooker\Contracts\CircuitBreaker::class),
        );

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_FAILED, $event->status);
    }

    public function test_delivery_sends_correct_signature_header(): void
    {
        Http::fake([
            'example.com/webhook' => Http::response('OK', 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'my-secret',
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

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\SignatureGenerator::class),
            app(\TechraysLabs\Webhooker\Contracts\RetryStrategy::class),
            app(\TechraysLabs\Webhooker\Contracts\CircuitBreaker::class),
        );

        Http::assertSent(function ($request) use ($event, $endpoint) {
            $payload = json_encode($event->payload);
            $expectedSignature = hash_hmac('sha256', $payload, $endpoint->secret);

            return $request->hasHeader('X-Webhook-Signature', $expectedSignature)
                && $request->url() === 'https://example.com/webhook';
        });
    }
}
