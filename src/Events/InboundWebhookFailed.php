<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Fired when inbound webhook processing fails.
 */
class InboundWebhookFailed
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $webhookEvent,
        public readonly ?\Throwable $exception = null,
    ) {}
}
