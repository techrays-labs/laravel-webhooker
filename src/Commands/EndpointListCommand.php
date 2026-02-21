<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Artisan command to list all registered webhook endpoints.
 */
class EndpointListCommand extends Command
{
    protected $signature = 'webhook:endpoint:list {--direction= : Filter by direction (inbound/outbound)} {--tag= : Filter by tag}';

    protected $description = 'List all registered webhook endpoints';

    public function handle(WebhookRepository $repository): int
    {
        $direction = $this->option('direction');
        $tag = $this->option('tag');

        if ($tag !== null) {
            $endpoints = $repository->getEndpointsByTag($tag);
            if ($direction !== null) {
                $endpoints = $endpoints->filter(fn ($e) => $e->direction === $direction);
            }
        } else {
            $endpoints = $repository->getActiveEndpoints($direction);
        }

        if ($endpoints->isEmpty()) {
            $this->info('No endpoints found.');

            return self::SUCCESS;
        }

        $rows = $endpoints->map(function ($endpoint) {
            $tagNames = $endpoint->tags->pluck('tag')->implode(', ');

            return [
                $endpoint->route_token,
                $endpoint->name,
                $endpoint->url,
                $endpoint->direction,
                $endpoint->is_active ? 'Yes' : 'No',
                $endpoint->timeout_seconds.'s',
                $tagNames,
            ];
        })->toArray();

        $this->table(
            ['Token', 'Name', 'URL', 'Direction', 'Active', 'Timeout', 'Tags'],
            $rows,
        );

        return self::SUCCESS;
    }
}
