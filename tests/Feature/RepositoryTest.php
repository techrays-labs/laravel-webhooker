<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Carbon;
use TechRaysLabs\Webhooker\Contracts\WebhookRepository;
use TechRaysLabs\Webhooker\Models\WebhookEndpoint;
use TechRaysLabs\Webhooker\Models\WebhookEvent;
use TechRaysLabs\Webhooker\Tests\TestCase;

class RepositoryTest extends TestCase
{
    private WebhookRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(WebhookRepository::class);
    }

    public function test_create_and_find_endpoint(): void
    {
        $endpoint = $this->repository->createEndpoint([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'secret-key',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $found = $this->repository->findEndpoint($endpoint->id);

        $this->assertNotNull($found);
        $this->assertEquals('Test', $found->name);
        $this->assertEquals('https://example.com/hook', $found->url);
    }

    public function test_get_active_endpoints_filters_by_direction(): void
    {
        $this->repository->createEndpoint([
            'name' => 'Outbound',
            'url' => 'https://example.com/out',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->repository->createEndpoint([
            'name' => 'Inbound',
            'url' => 'https://example.com/in',
            'direction' => 'inbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $this->repository->createEndpoint([
            'name' => 'Inactive',
            'url' => 'https://example.com/inactive',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => false,
            'timeout_seconds' => 30,
        ]);

        $allActive = $this->repository->getActiveEndpoints();
        $this->assertCount(2, $allActive);

        $outbound = $this->repository->getActiveEndpoints('outbound');
        $this->assertCount(1, $outbound);
        $this->assertEquals('Outbound', $outbound->first()->name);

        $inbound = $this->repository->getActiveEndpoints('inbound');
        $this->assertCount(1, $inbound);
        $this->assertEquals('Inbound', $inbound->first()->name);
    }

    public function test_create_and_find_event(): void
    {
        $endpoint = $this->createEndpoint();

        $event = $this->repository->createEvent([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['order_id' => 123],
            'status' => 'pending',
            'attempts_count' => 0,
        ]);

        $found = $this->repository->findEvent($event->id);

        $this->assertNotNull($found);
        $this->assertEquals('order.created', $found->event_name);
        $this->assertNotNull($found->endpoint);
    }

    public function test_get_retryable_events(): void
    {
        $endpoint = $this->createEndpoint();

        // Retryable: pending with past next_retry_at
        $this->repository->createEvent([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'retryable',
            'payload' => [],
            'status' => 'pending',
            'attempts_count' => 1,
            'next_retry_at' => Carbon::now()->subMinute(),
        ]);

        // Retryable: pending with null next_retry_at
        $this->repository->createEvent([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'new.pending',
            'payload' => [],
            'status' => 'pending',
            'attempts_count' => 0,
            'next_retry_at' => null,
        ]);

        // Not retryable: future next_retry_at
        $this->repository->createEvent([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'future.retry',
            'payload' => [],
            'status' => 'pending',
            'attempts_count' => 1,
            'next_retry_at' => Carbon::now()->addHour(),
        ]);

        // Not retryable: delivered
        $this->repository->createEvent([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'delivered',
            'payload' => [],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        $retryable = $this->repository->getRetryableEvents();
        $this->assertCount(2, $retryable);
    }

    public function test_prune_events(): void
    {
        $endpoint = $this->createEndpoint();

        $oldEvent = WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'old',
            'payload' => [],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);
        $oldEvent->created_at = Carbon::now()->subDays(40);
        $oldEvent->save();

        WebhookEvent::create([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'recent',
            'payload' => [],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        $deleted = $this->repository->pruneEvents(30);
        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, WebhookEvent::count());
    }

    public function test_create_attempt_and_get_for_event(): void
    {
        $endpoint = $this->createEndpoint();
        $event = $this->repository->createEvent([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'test',
            'payload' => [],
            'status' => 'pending',
            'attempts_count' => 0,
        ]);

        $this->repository->createAttempt([
            'event_id' => $event->id,
            'response_status' => 500,
            'response_body' => 'Error',
            'duration_ms' => 150,
            'attempted_at' => Carbon::now(),
        ]);

        $this->repository->createAttempt([
            'event_id' => $event->id,
            'response_status' => 200,
            'response_body' => 'OK',
            'duration_ms' => 85,
            'attempted_at' => Carbon::now(),
        ]);

        $attempts = $this->repository->getAttemptsForEvent($event->id);
        $this->assertCount(2, $attempts);
    }

    public function test_inbound_event_exists(): void
    {
        $endpoint = $this->createEndpoint('inbound');

        $this->assertFalse($this->repository->inboundEventExists($endpoint->id, 'event-123'));

        $this->repository->createEvent([
            'endpoint_id' => $endpoint->id,
            'event_name' => 'event-123',
            'payload' => [],
            'status' => 'delivered',
            'attempts_count' => 1,
        ]);

        $this->assertTrue($this->repository->inboundEventExists($endpoint->id, 'event-123'));
    }

    private function createEndpoint(string $direction = 'outbound'): WebhookEndpoint
    {
        return $this->repository->createEndpoint([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => $direction,
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }
}
