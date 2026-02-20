<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Strategies;

use Illuminate\Support\Carbon;
use TechRaysLabs\Webhooker\Contracts\RetryStrategy;

/**
 * Exponential backoff retry strategy.
 *
 * Calculates delay as: baseDelay * (multiplier ^ (attempt - 1))
 * e.g. with base=10s, multiplier=2: 10s, 20s, 40s, 80s, 160s
 */
class ExponentialBackoffRetry implements RetryStrategy
{
    public function __construct(
        private readonly int $maxAttempts = 5,
        private readonly int $baseDelaySeconds = 10,
        private readonly int $multiplier = 2,
    ) {}

    public function nextRetry(int $attempt): Carbon
    {
        $delaySeconds = $this->baseDelaySeconds * ($this->multiplier ** ($attempt - 1));

        return Carbon::now()->addSeconds((int) $delaySeconds);
    }

    public function shouldRetry(int $attempt): bool
    {
        return $attempt < $this->maxAttempts;
    }
}
