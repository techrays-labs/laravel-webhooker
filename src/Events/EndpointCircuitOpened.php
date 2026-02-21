<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;

/**
 * Fired when an endpoint's circuit breaker trips to OPEN state.
 */
class EndpointCircuitOpened
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEndpoint $endpoint,
        public readonly int $failureCount,
    ) {}
}
