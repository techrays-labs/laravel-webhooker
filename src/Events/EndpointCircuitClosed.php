<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;

/**
 * Fired when an endpoint's circuit breaker recovers to CLOSED state.
 */
class EndpointCircuitClosed
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEndpoint $endpoint,
    ) {}
}
