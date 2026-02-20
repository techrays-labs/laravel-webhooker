<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Fired after an inbound webhook is processed successfully.
 */
class InboundWebhookProcessed
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $webhookEvent,
    ) {}
}
