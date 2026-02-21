<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;
use TechraysLabs\Webhooker\Webhooker;

class IdempotencyTest extends TestCase
{
    public function test_duplicate_idempotency_key_returns_existing_event(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);

        $event1 = $webhooker->dispatch($endpoint->id, 'order.created', ['id' => 1], [
            'idempotency_key' => 'order-1-created',
        ]);

        $event2 = $webhooker->dispatch($endpoint->id, 'order.created', ['id' => 1], [
            'idempotency_key' => 'order-1-created',
        ]);

        $this->assertEquals($event1->id, $event2->id);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_different_idempotency_keys_create_separate_events(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);

        $event1 = $webhooker->dispatch($endpoint->id, 'order.created', ['id' => 1], [
            'idempotency_key' => 'order-1-created',
        ]);

        $event2 = $webhooker->dispatch($endpoint->id, 'order.created', ['id' => 2], [
            'idempotency_key' => 'order-2-created',
        ]);

        $this->assertNotEquals($event1->id, $event2->id);
        $this->assertDatabaseCount('webhook_events', 2);
    }

    public function test_no_idempotency_key_always_creates_event(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);

        $webhooker->dispatch($endpoint->id, 'order.created', ['id' => 1]);
        $webhooker->dispatch($endpoint->id, 'order.created', ['id' => 1]);

        $this->assertDatabaseCount('webhook_events', 2);
    }

    public function test_idempotency_key_stored_on_event(): void
    {
        Queue::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);
        $event = $webhooker->dispatch($endpoint->id, 'order.created', ['id' => 1], [
            'idempotency_key' => 'my-key-123',
        ]);

        $this->assertEquals('my-key-123', $event->idempotency_key);
    }
}
