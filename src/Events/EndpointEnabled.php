<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;

/**
 * Fired when a previously disabled endpoint is re-enabled.
 */
class EndpointEnabled
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEndpoint $endpoint,
    ) {}
}
