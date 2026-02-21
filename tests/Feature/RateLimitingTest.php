<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Contracts\RetryStrategy;
use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;

class RateLimitingTest extends TestCase
{
    public function test_rate_limiting_disabled_by_default(): void
    {
        $this->assertFalse(config('webhooks.rate_limiting.enabled'));
    }

    public function test_delivery_proceeds_when_rate_limiting_disabled(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);
        config(['webhooks.rate_limiting.enabled' => false]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(WebhookRepository::class),
            app(SignatureGenerator::class),
            app(RetryStrategy::class),
            app(CircuitBreaker::class),
        );

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_DELIVERED, $event->status);
    }

    public function test_delivery_delayed_when_rate_limit_exceeded(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('OK', 200)]);
        config(['webhooks.rate_limiting.enabled' => true, 'webhooks.rate_limiting.default_per_minute' => 1]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        // Use up the rate limit
        $key = "webhooker:rate:{$endpoint->id}";
        RateLimiter::hit($key, 60);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(WebhookRepository::class),
            app(SignatureGenerator::class),
            app(RetryStrategy::class),
            app(CircuitBreaker::class),
        );

        // Event should still be pending (not delivered)
        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);

        // A delayed job should have been re-dispatched
        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_per_endpoint_rate_limit_overrides_global(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('OK', 200)]);
        config(['webhooks.rate_limiting.enabled' => true, 'webhooks.rate_limiting.default_per_minute' => 100]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
            'rate_limit_per_minute' => 1,
        ]);

        // Use up the per-endpoint rate limit
        $key = "webhooker:rate:{$endpoint->id}";
        RateLimiter::hit($key, 60);

        $event = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(WebhookRepository::class),
            app(SignatureGenerator::class),
            app(RetryStrategy::class),
            app(CircuitBreaker::class),
        );

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        Queue::assertPushed(DispatchWebhookJob::class);
    }
}
