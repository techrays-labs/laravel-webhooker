<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\DTOs;

use Illuminate\Support\Carbon;

/**
 * Data transfer object for endpoint health information.
 */
class EndpointHealth
{
    public function __construct(
        public readonly int $endpointId,
        public readonly string $endpointName,
        public readonly float $successRate,
        public readonly float $averageResponseTimeMs,
        public readonly ?Carbon $lastSuccessAt,
        public readonly ?Carbon $lastFailureAt,
        public readonly string $status,
    ) {}
}
