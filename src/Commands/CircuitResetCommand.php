<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Artisan command to reset the circuit breaker for a specific endpoint.
 */
class CircuitResetCommand extends Command
{
    protected $signature = 'webhook:circuit:reset {endpoint_id : The ID of the endpoint to reset}';

    protected $description = 'Reset the circuit breaker for a webhook endpoint';

    public function handle(WebhookRepository $repository, CircuitBreaker $circuitBreaker): int
    {
        $endpointId = (int) $this->argument('endpoint_id');
        $endpoint = $repository->findEndpoint($endpointId);

        if ($endpoint === null) {
            $this->error("Endpoint #{$endpointId} not found.");

            return self::FAILURE;
        }

        $previousState = $circuitBreaker->getState($endpoint);
        $circuitBreaker->reset($endpoint);

        $this->info("Circuit breaker for endpoint '{$endpoint->name}' has been reset from {$previousState->value} to closed.");

        return self::SUCCESS;
    }
}
