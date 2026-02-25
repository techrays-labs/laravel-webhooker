<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Fired when a webhook event is moved to the dead-letter queue.
 */
class WebhookMovedToDeadLetter
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $webhookEvent,
        public readonly WebhookEndpoint $endpoint,
        public readonly string $reason,
    ) {}
}
