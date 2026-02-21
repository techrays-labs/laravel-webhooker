<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use TechraysLabs\Webhooker\Exceptions\InvalidWebhookPayloadException;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;
use TechraysLabs\Webhooker\Webhooker;

class PayloadValidationTest extends TestCase
{
    public function test_validation_disabled_by_default(): void
    {
        $this->assertFalse(config('webhooks.payload_validation.enabled'));
    }

    public function test_valid_payload_passes(): void
    {
        Queue::fake();

        config([
            'webhooks.payload_validation.enabled' => true,
            'webhooks.payload_validation.schemas' => [
                'order.created' => [
                    'order_id' => 'required|integer',
                    'amount' => 'required|numeric',
                ],
            ],
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);
        $event = $webhooker->dispatch($endpoint->id, 'order.created', [
            'order_id' => 123,
            'amount' => 99.99,
        ]);

        $this->assertNotNull($event->id);
    }

    public function test_invalid_payload_throws_exception(): void
    {
        config([
            'webhooks.payload_validation.enabled' => true,
            'webhooks.payload_validation.schemas' => [
                'order.created' => [
                    'order_id' => 'required|integer',
                    'amount' => 'required|numeric',
                ],
            ],
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);

        $this->expectException(InvalidWebhookPayloadException::class);
        $webhooker->dispatch($endpoint->id, 'order.created', ['order_id' => 'not-an-int']);
    }

    public function test_event_without_schema_passes(): void
    {
        Queue::fake();

        config([
            'webhooks.payload_validation.enabled' => true,
            'webhooks.payload_validation.schemas' => [],
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $webhooker = app(Webhooker::class);
        $event = $webhooker->dispatch($endpoint->id, 'order.created', ['any' => 'data']);

        $this->assertNotNull($event->id);
    }

    public function test_exception_contains_event_name_and_errors(): void
    {
        config([
            'webhooks.payload_validation.enabled' => true,
            'webhooks.payload_validation.schemas' => [
                'order.created' => [
                    'order_id' => 'required|integer',
                ],
            ],
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        try {
            app(Webhooker::class)->dispatch($endpoint->id, 'order.created', []);
            $this->fail('Expected InvalidWebhookPayloadException');
        } catch (InvalidWebhookPayloadException $e) {
            $this->assertEquals('order.created', $e->eventName);
            $this->assertTrue($e->errors->has('order_id'));
        }
    }
}
