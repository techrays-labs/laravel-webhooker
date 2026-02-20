<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Artisan command to show the health status of all webhook endpoints.
 */
class HealthCommand extends Command
{
    protected $signature = 'webhook:health';

    protected $description = 'Show health status of all webhook endpoints';

    public function handle(WebhookRepository $repository, WebhookMetrics $metrics): int
    {
        $endpoints = $repository->getActiveEndpoints();

        if ($endpoints->isEmpty()) {
            $this->info('No active endpoints found.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($endpoints as $endpoint) {
            $health = $metrics->endpointHealth($endpoint->id);
            $rows[] = [
                $endpoint->route_token,
                $endpoint->name,
                $endpoint->direction,
                $health->successRate.'%',
                $health->averageResponseTimeMs.'ms',
                $health->status,
            ];
        }

        $this->table(
            ['Token', 'Name', 'Direction', 'Success Rate', 'Avg Response', 'Status'],
            $rows,
        );

        return self::SUCCESS;
    }
}
