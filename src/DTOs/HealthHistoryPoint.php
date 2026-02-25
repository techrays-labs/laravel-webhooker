<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\DTOs;

use Illuminate\Support\Carbon;

/**
 * Data transfer object for a single health history data point.
 */
class HealthHistoryPoint
{
    public function __construct(
        public readonly float $successRate,
        public readonly float $averageResponseTimeMs,
        public readonly string $status,
        public readonly Carbon $recordedAt,
    ) {}
}
