<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use TechRaysLabs\Webhooker\Jobs\ProcessInboundWebhookJob;
use TechRaysLabs\Webhooker\Models\WebhookEndpoint;
use TechRaysLabs\Webhooker\Models\WebhookEvent;
use TechRaysLabs\Webhooker\Tests\TestCase;

class InboundWebhookTest extends TestCase
{
    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Inbound Test',
            'url' => 'https://example.com',
            'direction' => 'inbound',
            'secret' => 'inbound-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_accepts_valid_inbound_webhook(): void
    {
        Queue::fake();

        $payload = json_encode(['event' => 'payment.completed', 'amount' => 100]);
        $signature = hash_hmac('sha256', $payload, 'inbound-secret');

        $response = $this->postJson(
            route('webhooker.inbound', $this->endpoint->id),
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => 'payment.completed',
            ]
        );

        $response->assertStatus(202);
        $response->assertJson(['status' => 'accepted']);

        $this->assertDatabaseHas('webhook_events', [
            'endpoint_id' => $this->endpoint->id,
            'status' => WebhookEvent::STATUS_PENDING,
        ]);

        Queue::assertPushed(ProcessInboundWebhookJob::class);
    }

    public function test_rejects_missing_signature(): void
    {
        $response = $this->postJson(
            route('webhooker.inbound', $this->endpoint->id),
            ['event' => 'test'],
        );

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Missing signature.']);
    }

    public function test_rejects_invalid_signature(): void
    {
        $response = $this->postJson(
            route('webhooker.inbound', $this->endpoint->id),
            ['event' => 'test'],
            ['X-Webhook-Signature' => 'invalid-signature'],
        );

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid signature.']);
    }

    public function test_rejects_nonexistent_endpoint(): void
    {
        $payload = json_encode(['event' => 'test']);
        $signature = hash_hmac('sha256', $payload, 'inbound-secret');

        $response = $this->postJson(
            '/api/webhooks/inbound/99999',
            json_decode($payload, true),
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(404);
    }

    public function test_rejects_outbound_endpoint(): void
    {
        $outbound = WebhookEndpoint::create([
            'name' => 'Outbound',
            'url' => 'https://example.com',
            'direction' => 'outbound',
            'secret' => 'outbound-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);

        $payload = json_encode(['event' => 'test']);
        $signature = hash_hmac('sha256', $payload, 'outbound-secret');

        $response = $this->postJson(
            route('webhooker.inbound', $outbound->id),
            json_decode($payload, true),
            ['X-Webhook-Signature' => $signature],
        );

        $response->assertStatus(404);
    }

    public function test_deduplicates_by_event_id_header(): void
    {
        Queue::fake();

        // Create an existing event with the same event name for deduplication
        WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
            'event_name' => 'unique-event-id-123',
            'payload' => ['original' => true],
            'status' => WebhookEvent::STATUS_DELIVERED,
            'attempts_count' => 1,
        ]);

        $payload = json_encode(['event' => 'payment.completed']);
        $signature = hash_hmac('sha256', $payload, 'inbound-secret');

        $response = $this->postJson(
            route('webhooker.inbound', $this->endpoint->id),
            json_decode($payload, true),
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event-ID' => 'unique-event-id-123',
            ],
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'duplicate']);
    }

    public function test_inbound_processing_job_marks_event_as_delivered(): void
    {
        $event = WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
            'event_name' => 'test.event',
            'payload' => ['key' => 'value'],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $job = new ProcessInboundWebhookJob($event->id);
        $job->handle(
            app(\TechRaysLabs\Webhooker\Contracts\WebhookRepository::class),
            app(\TechRaysLabs\Webhooker\Contracts\InboundProcessor::class),
        );

        $event->refresh();
        $this->assertEquals(WebhookEvent::STATUS_DELIVERED, $event->status);
    }
}
