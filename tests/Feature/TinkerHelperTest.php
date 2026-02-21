<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use TechraysLabs\Webhooker\DTOs\MetricsSummary;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;
use TechraysLabs\Webhooker\Webhooker;

class TinkerHelperTest extends TestCase
{
    private Webhooker $webhooker;

    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhooker = app(Webhooker::class);
        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_inspect_returns_event_details(): void
    {
        $event = WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
            'event_name' => 'order.created',
            'payload' => ['id' => 1],
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        $result = $this->webhooker->inspect($event->id);

        $this->assertNotNull($result);
        $this->assertEquals($event->id, $result['id']);
        $this->assertEquals('order.created', $result['event_name']);
        $this->assertArrayHasKey('attempts', $result);
    }

    public function test_inspect_returns_null_for_nonexistent(): void
    {
        $this->assertNull($this->webhooker->inspect(9999));
    }

    public function test_last_failed_returns_most_recent_failed_event(): void
    {
        WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
            'event_name' => 'first.failed',
            'payload' => [],
            'status' => WebhookEvent::STATUS_FAILED,
            'attempts_count' => 3,
        ]);

        $latest = WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
            'event_name' => 'latest.failed',
            'payload' => [],
            'status' => WebhookEvent::STATUS_FAILED,
            'attempts_count' => 5,
        ]);

        $result = $this->webhooker->lastFailed();

        $this->assertNotNull($result);
        $this->assertEquals($latest->id, $result->id);
    }

    public function test_last_failed_returns_null_when_no_failures(): void
    {
        $this->assertNull($this->webhooker->lastFailed());
    }

    public function test_stats_returns_metrics_summary(): void
    {
        $result = $this->webhooker->stats();

        $this->assertInstanceOf(MetricsSummary::class, $result);
    }
}
