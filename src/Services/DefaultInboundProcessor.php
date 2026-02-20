<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use TechraysLabs\Webhooker\Contracts\InboundProcessor;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Default inbound processor that marks events as delivered.
 *
 * Applications should bind their own InboundProcessor implementation
 * in a service provider to handle inbound webhook payloads with
 * custom business logic.
 */
class DefaultInboundProcessor implements InboundProcessor
{
    public function process(WebhookEvent $event): bool
    {
        // Default implementation simply marks as delivered.
        // Override this binding in your application's service provider
        // to implement custom inbound webhook handling logic.
        return true;
    }
}
