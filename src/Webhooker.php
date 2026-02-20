<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker;

use TechRaysLabs\Webhooker\Contracts\WebhookRepository;
use TechRaysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechRaysLabs\Webhooker\Models\WebhookEndpoint;
use TechRaysLabs\Webhooker\Models\WebhookEvent;

/**
 * Main entry point for dispatching outbound webhook events.
 *
 * Usage:
 *   app(Webhooker::class)->dispatch($endpointId, 'order.created', ['order_id' => 123]);
 */
class Webhooker
{
    public function __construct(
        private readonly WebhookRepository $repository,
    ) {}

    /**
     * Dispatch a webhook event to a specific endpoint.
     *
     * Persists the event and queues it for async delivery.
     *
     * @param  int  $endpointId  The endpoint to deliver to.
     * @param  string  $eventName  The event type name.
     * @param  array<string, mixed>  $payload  The event payload.
     */
    public function dispatch(int $endpointId, string $eventName, array $payload): WebhookEvent
    {
        $event = $this->repository->createEvent([
            'endpoint_id' => $endpointId,
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        DispatchWebhookJob::dispatch($event->id);

        return $event;
    }

    /**
     * Dispatch a webhook event to all active outbound endpoints.
     *
     * @param  string  $eventName  The event type name.
     * @param  array<string, mixed>  $payload  The event payload.
     * @return array<WebhookEvent>
     */
    public function broadcast(string $eventName, array $payload): array
    {
        $endpoints = $this->repository->getActiveEndpoints('outbound');
        $events = [];

        foreach ($endpoints as $endpoint) {
            $events[] = $this->dispatch($endpoint->id, $eventName, $payload);
        }

        return $events;
    }

    /**
     * Register a new webhook endpoint.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function registerEndpoint(array $attributes): WebhookEndpoint
    {
        return $this->repository->createEndpoint($attributes);
    }
}
