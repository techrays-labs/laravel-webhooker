<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookDeliveryCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $eventId,
        public string $eventName,
        public int $endpointId,
        public string $endpointName,
        public string $status,
        public ?int $responseTimeMs = null,
        public ?string $errorMessage = null
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('webhooks.delivery'),
            new PrivateChannel('webhooks.endpoint.'.$this->endpointId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->eventName,
            'endpoint_id' => $this->endpointId,
            'endpoint_name' => $this->endpointName,
            'status' => $this->status,
            'response_time_ms' => $this->responseTimeMs,
            'error_message' => $this->errorMessage,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
