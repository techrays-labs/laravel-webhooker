<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Contracts;

use TechRaysLabs\Webhooker\Models\WebhookEvent;

/**
 * Contract for processing inbound webhook events.
 *
 * Implement this interface with your application-specific logic for
 * handling received webhook payloads.
 */
interface InboundProcessor
{
    /**
     * Process an inbound webhook event.
     *
     * @return bool True if processing succeeded, false otherwise.
     */
    public function process(WebhookEvent $event): bool;
}
