<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Fired after all retry attempts have been exhausted for an outbound webhook.
 */
class WebhookRetriesExhausted
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $webhookEvent,
        public readonly WebhookEndpoint $endpoint,
    ) {}
}
