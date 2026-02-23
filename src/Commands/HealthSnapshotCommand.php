<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\WebhookLock;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Artisan command to capture health snapshots for all active endpoints.
 *
 * Intended to be scheduled (e.g., hourly) via Laravel's scheduler.
 */
class HealthSnapshotCommand extends Command
{
    protected $signature = 'webhook:health:snapshot';

    protected $description = 'Capture health snapshots for all active endpoints';

    public function handle(WebhookRepository $repository, WebhookMetrics $metrics, WebhookLock $lock): int
    {
        if (config('webhooks.scaling.enabled', false)) {
            if (! $lock->acquireNamedLock('webhooker:health_snapshot', 3600)) {
                $this->info('Another instance is running the snapshot. Skipping.');

                return self::SUCCESS;
            }
        }

        $endpoints = $repository->getActiveEndpoints();
        $count = 0;

        foreach ($endpoints as $endpoint) {
            $health = $metrics->endpointHealth($endpoint->id);

            $repository->createHealthSnapshot([
                'endpoint_id' => $endpoint->id,
                'success_rate' => $health->successRate,
                'average_response_time_ms' => $health->averageResponseTimeMs,
                'total_events' => 0,
                'failed_events' => 0,
                'status' => $health->status,
                'recorded_at' => now(),
            ]);

            $count++;
        }

        $this->info("Captured health snapshots for {$count} endpoint(s).");

        return self::SUCCESS;
    }
}
