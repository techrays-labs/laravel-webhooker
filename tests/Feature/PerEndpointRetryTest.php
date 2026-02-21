<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use TechraysLabs\Webhooker\Contracts\RetryStrategy;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Strategies\ExponentialBackoffRetry;
use TechraysLabs\Webhooker\Tests\TestCase;

class PerEndpointRetryTest extends TestCase
{
    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhooks.circuit_breaker.enabled' => false]);

        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Retry Test Endpoint',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_global_retry_used_when_no_per_endpoint_config(): void
    {
        config(['webhooks.retry.max_attempts' => 3]);

        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
            'event_name' => 'test.event',
            'payload' => ['key' => 'value'],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 2,
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\SignatureGenerator::class),
            app(\TechraysLabs\Webhooker\Contracts\RetryStrategy::class),
            app(\TechraysLabs\Webhooker\Contracts\CircuitBreaker::class),
        );

        $event->refresh();
        // With max_attempts=3, attempt #3 should exhaust retries
        $this->assertEquals(WebhookEvent::STATUS_FAILED, $event->status);
    }

    public function test_per_endpoint_max_retries_overrides_global(): void
    {
        Queue::fake();

        config(['webhooks.retry.max_attempts' => 3]);

        // Set per-endpoint to allow 10 retries
        $this->endpoint->update(['max_retries' => 10]);

        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
            'event_name' => 'test.event',
            'payload' => ['key' => 'value'],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 2,
        ]);

        $job = new DispatchWebhookJob($event->id);
        $job->handle(
            app(\TechraysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechraysLabs\Webhooker\Contracts\SignatureGenerator::class),
            app(\TechraysLabs\Webhooker\Contracts\RetryStrategy::class),
            app(\TechraysLabs\Webhooker\Contracts\CircuitBreaker::class),
        );

        $event->refresh();
        // Should still be pending since per-endpoint allows 10 retries
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
        $this->assertNotNull($event->next_retry_at);
    }

    public function test_per_endpoint_strategy_class_overrides_global(): void
    {
        Queue::fake();

        $this->endpoint->update([
            'retry_strategy' => ExponentialBackoffRetry::class,
            'max_retries' => null,
        ]);

        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
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

        $event->refresh();
        // Default ExponentialBackoffRetry allows 5 attempts, so first failure should retry
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
    }

    public function test_invalid_strategy_class_falls_back_to_global(): void
    {
        Queue::fake();

        config(['webhooks.retry.max_attempts' => 3]);

        $this->endpoint->update([
            'retry_strategy' => 'NonExistentClass',
        ]);

        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $event = WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
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

        $event->refresh();
        // Should use global strategy (max 3 attempts), first failure should retry
        $this->assertEquals(WebhookEvent::STATUS_PENDING, $event->status);
    }

    public function test_null_per_endpoint_values_use_global(): void
    {
        $this->assertNull($this->endpoint->max_retries);
        $this->assertNull($this->endpoint->retry_strategy);

        // This verifies the model returns null for unconfigured endpoints
        $globalStrategy = app(RetryStrategy::class);
        $this->assertInstanceOf(ExponentialBackoffRetry::class, $globalStrategy);
    }
}
