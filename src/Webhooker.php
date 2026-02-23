<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker;

use Illuminate\Support\Str;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\DTOs\MetricsSummary;
use TechraysLabs\Webhooker\Events\EndpointSecretRotated;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookBatch;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;

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
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    /**
     * Dispatch a webhook event to a specific endpoint.
     *
     * Persists the event and queues it for async delivery.
     *
     * @param  int  $endpointId  The endpoint to deliver to.
     * @param  string  $eventName  The event type name.
     * @param  array<string, mixed>  $payload  The event payload.
     * @param  array<string, mixed>  $options  Optional dispatch options (idempotency_key, etc).
     */
    public function dispatch(int $endpointId, string $eventName, array $payload, array $options = []): WebhookEvent
    {
        // Payload validation
        app(Contracts\PayloadValidator::class)->validate($eventName, $payload);

        // Idempotency check
        $idempotencyKey = $options['idempotency_key'] ?? null;
        if ($idempotencyKey !== null) {
            $existing = $this->repository->findEventByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        $endpoint = $this->repository->findEndpoint($endpointId);

        if ($endpoint !== null && ! $this->circuitBreaker->isAvailable($endpoint)) {
            return $this->repository->createEvent([
                'endpoint_id' => $endpointId,
                'event_name' => $eventName,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
                'status' => WebhookEvent::STATUS_PENDING,
                'attempts_count' => 0,
            ]);
        }

        $event = $this->repository->createEvent([
            'endpoint_id' => $endpointId,
            'event_name' => $eventName,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
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
     * Dispatch a webhook event to all endpoints with a given tag.
     *
     * @param  string  $tag  The tag to match.
     * @param  string  $eventName  The event type name.
     * @param  array<string, mixed>  $payload  The event payload.
     * @return array<WebhookEvent>
     */
    public function dispatchToTag(string $tag, string $eventName, array $payload): array
    {
        $endpoints = $this->repository->getEndpointsByTag($tag);
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

    /**
     * Disable an endpoint.
     */
    public function disable(int $endpointId, ?string $reason = null): void
    {
        $endpoint = $this->repository->findEndpoint($endpointId);

        if ($endpoint === null) {
            return;
        }

        $this->repository->updateEndpoint($endpoint, [
            'is_active' => false,
            'disabled_at' => now(),
            'disabled_reason' => $reason,
        ]);

        \TechraysLabs\Webhooker\Events\EndpointDisabled::dispatch($endpoint, $reason);
    }

    /**
     * Enable an endpoint.
     */
    public function enable(int $endpointId): void
    {
        $endpoint = $this->repository->findEndpoint($endpointId);

        if ($endpoint === null) {
            return;
        }

        $this->repository->updateEndpoint($endpoint, [
            'is_active' => true,
            'disabled_at' => null,
            'disabled_reason' => null,
        ]);

        \TechraysLabs\Webhooker\Events\EndpointEnabled::dispatch($endpoint);
    }

    /**
     * Check if an endpoint is enabled.
     */
    public function isEnabled(int $endpointId): bool
    {
        $endpoint = $this->repository->findEndpoint($endpointId);

        if ($endpoint === null) {
            return false;
        }

        return $endpoint->is_active && ! $endpoint->isDisabled();
    }

    /**
     * Inspect a webhook event and its attempts (Tinker helper).
     *
     * @return array<string, mixed>|null
     */
    public function inspect(int $eventId): ?array
    {
        $event = $this->repository->findEvent($eventId);

        if ($event === null) {
            return null;
        }

        $attempts = $this->repository->getAttemptsForEvent($eventId);

        return [
            'id' => $event->id,
            'endpoint_id' => $event->endpoint_id,
            'event_name' => $event->event_name,
            'status' => $event->status,
            'attempts_count' => $event->attempts_count,
            'created_at' => $event->created_at->toIso8601String(),
            'last_attempt_at' => $event->last_attempt_at?->toIso8601String(),
            'next_retry_at' => $event->next_retry_at?->toIso8601String(),
            'attempts' => $attempts->map(fn ($a) => [
                'status' => $a->response_status,
                'duration_ms' => $a->duration_ms,
                'error' => $a->error_message,
                'at' => $a->attempted_at->toIso8601String(),
            ])->toArray(),
        ];
    }

    /**
     * Get the most recent failed event (Tinker helper).
     */
    public function lastFailed(): ?WebhookEvent
    {
        return $this->repository->getLastFailedEvent();
    }

    /**
     * Retry the most recent failed event (Tinker helper).
     */
    public function retryLast(): ?WebhookEvent
    {
        $event = $this->lastFailed();

        if ($event === null) {
            return null;
        }

        $this->repository->updateEvent($event, [
            'status' => WebhookEvent::STATUS_PENDING,
            'next_retry_at' => null,
        ]);

        DispatchWebhookJob::dispatch($event->id);

        return $event->fresh();
    }

    /**
     * Rotate the secret for an endpoint.
     *
     * @return string The new secret.
     */
    public function rotateSecret(int $endpointId): string
    {
        $endpoint = $this->repository->findEndpoint($endpointId);

        if ($endpoint === null) {
            throw new \InvalidArgumentException("Endpoint #{$endpointId} not found.");
        }

        $newSecret = Str::random(64);

        $this->repository->updateEndpoint($endpoint, [
            'previous_secret' => $endpoint->secret,
            'secret_rotated_at' => now(),
            'secret' => $newSecret,
        ]);

        EndpointSecretRotated::dispatch($endpoint->fresh());

        return $newSecret;
    }

    /**
     * Dispatch a batch of events to multiple endpoints.
     *
     * @param  array<int, int>  $endpointIds
     * @param  string  $eventName
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options  Optional: metadata
     */
    public function dispatchBatch(array $endpointIds, string $eventName, array $payload, array $options = []): WebhookBatch
    {
        $maxBatchSize = (int) config('webhooks.batching.max_batch_size', 1000);
        $endpointIds = array_slice($endpointIds, 0, $maxBatchSize);

        $batchId = (string) Str::uuid();
        $batch = $this->repository->createBatch([
            'batch_id' => $batchId,
            'event_name' => $eventName,
            'total_events' => count($endpointIds),
            'pending_count' => count($endpointIds),
            'status' => WebhookBatch::STATUS_PROCESSING,
            'metadata' => $options['metadata'] ?? null,
        ]);

        foreach ($endpointIds as $endpointId) {
            $event = $this->repository->createEvent([
                'endpoint_id' => $endpointId,
                'event_name' => $eventName,
                'payload' => $payload,
                'batch_id' => $batchId,
                'status' => WebhookEvent::STATUS_PENDING,
                'attempts_count' => 0,
            ]);

            DispatchWebhookJob::dispatch($event->id);
        }

        return $batch;
    }

    /**
     * Broadcast an event as a batch to all active outbound endpoints.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    public function broadcastBatch(string $eventName, array $payload, array $options = []): WebhookBatch
    {
        $endpoints = $this->repository->getActiveEndpoints('outbound');

        return $this->dispatchBatch(
            $endpoints->pluck('id')->toArray(),
            $eventName,
            $payload,
            $options,
        );
    }

    /**
     * Get the status of a batch.
     */
    public function batchStatus(string $batchId): ?WebhookBatch
    {
        return $this->repository->findBatch($batchId);
    }

    /**
     * Get a quick metrics summary (Tinker helper).
     */
    public function stats(string $direction = 'outbound'): MetricsSummary
    {
        return app(WebhookMetrics::class)->summary($direction);
    }
}
