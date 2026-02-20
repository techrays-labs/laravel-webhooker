<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Fired when a webhook event replay is triggered.
 */
class WebhookReplayRequested
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookEvent $webhookEvent,
    ) {}
}
