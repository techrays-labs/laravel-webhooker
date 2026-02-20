<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Unit;

use Illuminate\Support\Carbon;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\Models\WebhookAttempt;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;

class WebhookMetricsTest extends TestCase
{
    private WebhookMetrics $metrics;

    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metrics = app(WebhookMetrics::class);
        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'direction' => 'outbound',
            'secret' => 'secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_summary_returns_correct_counts(): void
    {
        $this->createEvent('delivered', 1);
        $this->createEvent('delivered', 1);
        $this->createEvent('failed', 3);
        $this->createEvent('pending', 0);

        $summary = $this->metrics->summary('outbound');

        $this->assertEquals(4, $summary->totalEvents);
        $this->assertEquals(2, $summary->successfulCount);
        $this->assertEquals(1, $summary->failedCount);
        $this->assertEquals(1, $summary->pendingCount);
    }

    public function test_summary_with_date_range(): void
    {
        $old = $this->createEvent('delivered', 1);
        $old->created_at = Carbon::now()->subDays(10);
        $old->save();

        $this->createEvent('delivered', 1);

        $summary = $this->metrics->summary('outbound', Carbon::now()->subDay());

        $this->assertEquals(1, $summary->totalEvents);
    }

    public function test_failure_rate_calculation(): void
    {
        $this->createEvent('delivered', 1);
        $this->createEvent('delivered', 1);
        $this->createEvent('delivered', 1);
        $this->createEvent('failed', 3);

        $rate = $this->metrics->failureRate('outbound');

        $this->assertEquals(25.0, $rate);
    }

    public function test_failure_rate_returns_zero_with_no_events(): void
    {
        $rate = $this->metrics->failureRate('outbound');
        $this->assertEquals(0.0, $rate);
    }

    public function test_average_response_time(): void
    {
        $event = $this->createEvent('delivered', 1);

        WebhookAttempt::create([
            'event_id' => $event->id,
            'response_status' => 200,
            'duration_ms' => 100,
            'attempted_at' => Carbon::now(),
        ]);

        WebhookAttempt::create([
            'event_id' => $event->id,
            'response_status' => 200,
            'duration_ms' => 200,
            'attempted_at' => Carbon::now(),
        ]);

        $avgTime = $this->metrics->averageResponseTime($this->endpoint->id);

        $this->assertEquals(150.0, $avgTime);
    }

    public function test_endpoint_health_returns_healthy_status(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createEvent('delivered', 1);
        }

        $health = $this->metrics->endpointHealth($this->endpoint->id);

        $this->assertEquals('healthy', $health->status);
        $this->assertEquals(100.0, $health->successRate);
        $this->assertEquals($this->endpoint->name, $health->endpointName);
    }

    public function test_endpoint_health_returns_degraded_status(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->createEvent('delivered', 1);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->createEvent('failed', 3);
        }

        $health = $this->metrics->endpointHealth($this->endpoint->id);

        $this->assertEquals('degraded', $health->status);
        $this->assertEquals(80.0, $health->successRate);
    }

    public function test_endpoint_health_returns_failing_status(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->createEvent('delivered', 1);
        }
        for ($i = 0; $i < 7; $i++) {
            $this->createEvent('failed', 3);
        }

        $health = $this->metrics->endpointHealth($this->endpoint->id);

        $this->assertEquals('failing', $health->status);
    }

    public function test_endpoint_health_returns_unknown_when_no_events(): void
    {
        $health = $this->metrics->endpointHealth($this->endpoint->id);

        $this->assertEquals('unknown', $health->status);
        $this->assertEquals(0.0, $health->successRate);
    }

    private function createEvent(string $status, int $attempts): WebhookEvent
    {
        return WebhookEvent::create([
            'endpoint_id' => $this->endpoint->id,
            'event_name' => 'test.event',
            'payload' => ['key' => 'value'],
            'status' => $status,
            'attempts_count' => $attempts,
        ]);
    }
}
