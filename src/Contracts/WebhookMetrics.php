<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Contracts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use TechraysLabs\Webhooker\DTOs\EndpointHealth;
use TechraysLabs\Webhooker\DTOs\MetricsSummary;

/**
 * Contract for webhook metrics aggregation.
 */
interface WebhookMetrics
{
    /**
     * Get a summary of webhook metrics for a given direction and time range.
     */
    public function summary(string $direction, ?Carbon $from = null, ?Carbon $to = null): MetricsSummary;

    /**
     * Get health information for a specific endpoint.
     */
    public function endpointHealth(int $endpointId): EndpointHealth;

    /**
     * Calculate the failure rate for a given direction and time range.
     */
    public function failureRate(string $direction, ?Carbon $from = null, ?Carbon $to = null): float;

    /**
     * Calculate the average response time for a specific endpoint.
     */
    public function averageResponseTime(int $endpointId, ?Carbon $from = null, ?Carbon $to = null): float;

    /**
     * Get the health history for an endpoint over a time range.
     *
     * @return Collection<int, \TechraysLabs\Webhooker\DTOs\HealthHistoryPoint>
     */
    public function endpointHealthHistory(int $endpointId, int $days = 30): Collection;
}
