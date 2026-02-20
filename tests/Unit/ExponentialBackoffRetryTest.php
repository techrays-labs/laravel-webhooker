<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Unit;

use Illuminate\Support\Carbon;
use TechraysLabs\Webhooker\Strategies\ExponentialBackoffRetry;
use TechraysLabs\Webhooker\Tests\TestCase;

class ExponentialBackoffRetryTest extends TestCase
{
    public function test_next_retry_uses_exponential_backoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));

        $strategy = new ExponentialBackoffRetry(
            maxAttempts: 5,
            baseDelaySeconds: 10,
            multiplier: 2,
        );

        // attempt 1: 10 * 2^0 = 10 seconds
        $next = $strategy->nextRetry(1);
        $this->assertEquals('2026-01-01 12:00:10', $next->toDateTimeString());

        // attempt 2: 10 * 2^1 = 20 seconds
        $next = $strategy->nextRetry(2);
        $this->assertEquals('2026-01-01 12:00:20', $next->toDateTimeString());

        // attempt 3: 10 * 2^2 = 40 seconds
        $next = $strategy->nextRetry(3);
        $this->assertEquals('2026-01-01 12:00:40', $next->toDateTimeString());

        // attempt 4: 10 * 2^3 = 80 seconds
        $next = $strategy->nextRetry(4);
        $this->assertEquals('2026-01-01 12:01:20', $next->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_should_retry_within_max_attempts(): void
    {
        $strategy = new ExponentialBackoffRetry(maxAttempts: 3);

        $this->assertTrue($strategy->shouldRetry(1));
        $this->assertTrue($strategy->shouldRetry(2));
        $this->assertFalse($strategy->shouldRetry(3));
        $this->assertFalse($strategy->shouldRetry(4));
    }

    public function test_custom_configuration_values(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));

        $strategy = new ExponentialBackoffRetry(
            maxAttempts: 10,
            baseDelaySeconds: 60,
            multiplier: 3,
        );

        // attempt 1: 60 * 3^0 = 60 seconds
        $next = $strategy->nextRetry(1);
        $this->assertEquals('2026-01-01 00:01:00', $next->toDateTimeString());

        // attempt 2: 60 * 3^1 = 180 seconds
        $next = $strategy->nextRetry(2);
        $this->assertEquals('2026-01-01 00:03:00', $next->toDateTimeString());

        $this->assertTrue($strategy->shouldRetry(9));
        $this->assertFalse($strategy->shouldRetry(10));

        Carbon::setTestNow();
    }
}
