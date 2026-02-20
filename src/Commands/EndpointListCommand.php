<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechRaysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Artisan command to list all registered webhook endpoints.
 */
class EndpointListCommand extends Command
{
    protected $signature = 'webhook:endpoint:list {--direction= : Filter by direction (inbound/outbound)}';

    protected $description = 'List all registered webhook endpoints';

    public function handle(WebhookRepository $repository): int
    {
        $direction = $this->option('direction');
        $endpoints = $repository->getActiveEndpoints($direction);

        if ($endpoints->isEmpty()) {
            $this->info('No endpoints found.');

            return self::SUCCESS;
        }

        $rows = $endpoints->map(function ($endpoint) {
            return [
                $endpoint->id,
                $endpoint->name,
                $endpoint->url,
                $endpoint->direction,
                $endpoint->is_active ? 'Yes' : 'No',
                $endpoint->timeout_seconds.'s',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Name', 'URL', 'Direction', 'Active', 'Timeout'],
            $rows,
        );

        return self::SUCCESS;
    }
}
