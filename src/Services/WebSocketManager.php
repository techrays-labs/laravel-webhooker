<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use TechraysLabs\Webhooker\Events\WebhookDeliveryCompleted;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;

class WebSocketManager
{
    public function isEnabled(): bool
    {
        return config('webhooks.websocket.enabled', false);
    }

    public function broadcastDelivery(WebhookEvent $event, ?int $responseTimeMs = null, ?string $errorMessage = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $endpoint = $event->endpoint;

        if (!$endpoint) {
            return;
        }

        event(new WebhookDeliveryCompleted(
            eventId: $event->id,
            eventName: $event->event_name,
            endpointId: $endpoint->id,
            endpointName: $endpoint->name,
            status: $event->status,
            responseTimeMs: $responseTimeMs,
            errorMessage: $errorMessage
        ));
    }
}
