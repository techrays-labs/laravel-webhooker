<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\DTOs;

/**
 * Data transfer object for aggregated webhook metrics.
 */
class MetricsSummary
{
    public function __construct(
        public readonly int $totalEvents,
        public readonly int $successfulCount,
        public readonly int $failedCount,
        public readonly int $pendingCount,
        public readonly float $averageAttemptsPerEvent,
        public readonly float $averageResponseTimeMs,
    ) {}
}
