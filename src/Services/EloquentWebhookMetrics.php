<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\DTOs\EndpointHealth;
use TechraysLabs\Webhooker\DTOs\MetricsSummary;
use TechraysLabs\Webhooker\Models\WebhookAttempt;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Eloquent-based implementation of the WebhookMetrics contract.
 */
class EloquentWebhookMetrics implements WebhookMetrics
{
    public function summary(string $direction, ?Carbon $from = null, ?Carbon $to = null): MetricsSummary
    {
        $fromKey = $from !== null ? (string) $from->timestamp : 'null';
        $toKey = $to !== null ? (string) $to->timestamp : 'null';
        $cacheKey = "webhooker:metrics:summary:{$direction}:{$fromKey}:{$toKey}";
        $ttl = (int) config('webhooks.metrics.cache_ttl', 60);

        return Cache::remember($cacheKey, $ttl, function () use ($direction, $from, $to): MetricsSummary {
            $query = WebhookEvent::query()
                ->whereHas('endpoint', function ($q) use ($direction) {
                    $q->where('direction', $direction);
                });

            if ($from !== null) {
                $query->where('created_at', '>=', $from);
            }
            if ($to !== null) {
                $query->where('created_at', '<=', $to);
            }

            $total = $query->count();
            $successful = (clone $query)->where('status', WebhookEvent::STATUS_DELIVERED)->count();
            $failed = (clone $query)->where('status', WebhookEvent::STATUS_FAILED)->count();
            $pending = (clone $query)->where('status', WebhookEvent::STATUS_PENDING)->count();

            $avgAttempts = $total > 0
                ? (float) (clone $query)->avg('attempts_count')
                : 0.0;

            $eventIds = (clone $query)->pluck('id');
            $avgResponseTime = $eventIds->isNotEmpty()
                ? (float) WebhookAttempt::whereIn('event_id', $eventIds)->avg('duration_ms')
                : 0.0;

            return new MetricsSummary(
                totalEvents: $total,
                successfulCount: $successful,
                failedCount: $failed,
                pendingCount: $pending,
                averageAttemptsPerEvent: round($avgAttempts, 2),
                averageResponseTimeMs: round($avgResponseTime, 2),
            );
        });
    }

    public function endpointHealth(int $endpointId): EndpointHealth
    {
        $cacheKey = "webhooker:metrics:health:{$endpointId}";
        $ttl = (int) config('webhooks.metrics.cache_ttl', 60);

        return Cache::remember($cacheKey, $ttl, function () use ($endpointId): EndpointHealth {
            $endpoint = WebhookEndpoint::findOrFail($endpointId);

            $recentEvents = WebhookEvent::where('endpoint_id', $endpointId)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();

            if ($recentEvents->isEmpty()) {
                return new EndpointHealth(
                    endpointId: $endpointId,
                    endpointName: $endpoint->name,
                    successRate: 0.0,
                    averageResponseTimeMs: 0.0,
                    lastSuccessAt: null,
                    lastFailureAt: null,
                    status: 'unknown',
                );
            }

            $total = $recentEvents->count();
            $successCount = $recentEvents->where('status', WebhookEvent::STATUS_DELIVERED)->count();
            $successRate = ($successCount / $total) * 100;

            $avgResponseTime = $this->averageResponseTime($endpointId);

            $lastSuccess = WebhookEvent::where('endpoint_id', $endpointId)
                ->where('status', WebhookEvent::STATUS_DELIVERED)
                ->orderByDesc('created_at')
                ->first();

            $lastFailure = WebhookEvent::where('endpoint_id', $endpointId)
                ->where('status', WebhookEvent::STATUS_FAILED)
                ->orderByDesc('created_at')
                ->first();

            $healthyThreshold = (float) config('webhooks.metrics.healthy_threshold', 95);
            $degradedThreshold = (float) config('webhooks.metrics.degraded_threshold', 70);

            $sevenDaysAgo = Carbon::now()->subDays(7);
            $hasRecentEvents = $recentEvents->first()->created_at->gte($sevenDaysAgo);

            if (! $hasRecentEvents) {
                $status = 'unknown';
            } elseif ($successRate >= $healthyThreshold) {
                $status = 'healthy';
            } elseif ($successRate >= $degradedThreshold) {
                $status = 'degraded';
            } else {
                $status = 'failing';
            }

            return new EndpointHealth(
                endpointId: $endpointId,
                endpointName: $endpoint->name,
                successRate: round($successRate, 2),
                averageResponseTimeMs: round($avgResponseTime, 2),
                lastSuccessAt: $lastSuccess?->created_at,
                lastFailureAt: $lastFailure?->created_at,
                status: $status,
            );
        });
    }

    public function failureRate(string $direction, ?Carbon $from = null, ?Carbon $to = null): float
    {
        $summary = $this->summary($direction, $from, $to);

        if ($summary->totalEvents === 0) {
            return 0.0;
        }

        return round(($summary->failedCount / $summary->totalEvents) * 100, 2);
    }

    public function averageResponseTime(int $endpointId, ?Carbon $from = null, ?Carbon $to = null): float
    {
        $query = WebhookAttempt::query()
            ->whereHas('event', function ($q) use ($endpointId) {
                $q->where('endpoint_id', $endpointId);
            });

        if ($from !== null) {
            $query->where('attempted_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('attempted_at', '<=', $to);
        }

        return round((float) $query->avg('duration_ms'), 2);
    }
}
