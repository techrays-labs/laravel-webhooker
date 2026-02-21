<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Artisan command to display circuit breaker status for all endpoints.
 */
class CircuitStatusCommand extends Command
{
    protected $signature = 'webhook:circuit:status';

    protected $description = 'Display circuit breaker status for all webhook endpoints';

    public function handle(WebhookRepository $repository, CircuitBreaker $circuitBreaker): int
    {
        $endpoints = $repository->getActiveEndpoints('outbound');

        if ($endpoints->isEmpty()) {
            $this->info('No outbound endpoints found.');

            return self::SUCCESS;
        }

        $rows = $endpoints->map(function ($endpoint) use ($circuitBreaker) {
            $state = $circuitBreaker->getState($endpoint);

            return [
                $endpoint->route_token,
                $endpoint->name,
                strtoupper($state->value),
                $circuitBreaker->isAvailable($endpoint) ? 'Yes' : 'No',
            ];
        })->toArray();

        $this->table(
            ['Token', 'Name', 'Circuit State', 'Available'],
            $rows,
        );

        return self::SUCCESS;
    }
}
