<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Testing;

use Illuminate\Support\Collection;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Webhooker;

/**
 * Fake replacement for the Webhooker service in tests.
 *
 * Captures all dispatched events without touching the database or network.
 */
class WebhookFake extends Webhooker
{
    /** @var array<int, array{endpoint_id: int, event_name: string, payload: array<string, mixed>}> */
    private array $dispatched = [];

    public function __construct()
    {
        // No parent constructor call — we don't need repository/circuit breaker
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    public function dispatch(int $endpointId, string $eventName, array $payload, array $options = []): WebhookEvent
    {
        $this->dispatched[] = [
            'endpoint_id' => $endpointId,
            'event_name' => $eventName,
            'payload' => $payload,
        ];

        $event = new WebhookEvent;
        $event->forceFill([
            'id' => count($this->dispatched),
            'endpoint_id' => $endpointId,
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<WebhookEvent>
     */
    public function broadcast(string $eventName, array $payload): array
    {
        return [$this->dispatch(0, $eventName, $payload)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<WebhookEvent>
     */
    public function dispatchToTag(string $tag, string $eventName, array $payload): array
    {
        return [$this->dispatch(0, $eventName, $payload)];
    }

    /**
     * Assert that a specific event was dispatched.
     *
     * @param  callable|null  $callback  Optional callback to inspect the event.
     */
    public function assertDispatched(string $eventName, ?callable $callback = null): void
    {
        $matching = $this->dispatched($eventName);

        \PHPUnit\Framework\Assert::assertTrue(
            $matching->isNotEmpty(),
            "The expected webhook event [{$eventName}] was not dispatched."
        );

        if ($callback !== null) {
            $filtered = $matching->filter(function ($record) use ($callback) {
                $event = new WebhookEvent;
                $event->forceFill([
                    'event_name' => $record['event_name'],
                    'payload' => $record['payload'],
                    'endpoint_id' => $record['endpoint_id'],
                ]);

                return $callback($event);
            });

            \PHPUnit\Framework\Assert::assertTrue(
                $filtered->isNotEmpty(),
                "The expected webhook event [{$eventName}] was dispatched but the callback condition was not met."
            );
        }
    }

    /**
     * Assert that no webhooks were dispatched.
     */
    public function assertNothingDispatched(): void
    {
        \PHPUnit\Framework\Assert::assertEmpty(
            $this->dispatched,
            'Webhook events were dispatched unexpectedly. Dispatched: '.implode(', ', array_column($this->dispatched, 'event_name'))
        );
    }

    /**
     * Assert a specific event was dispatched a given number of times.
     */
    public function assertDispatchedTimes(string $eventName, int $times): void
    {
        $count = $this->dispatched($eventName)->count();

        \PHPUnit\Framework\Assert::assertEquals(
            $times,
            $count,
            "Expected [{$eventName}] to be dispatched {$times} times, but was dispatched {$count} times."
        );
    }

    /**
     * Get all dispatched records for a given event name.
     *
     * @return Collection<int, array{endpoint_id: int, event_name: string, payload: array<string, mixed>}>
     */
    public function dispatched(string $eventName): Collection
    {
        return collect($this->dispatched)->filter(
            fn ($record) => $record['event_name'] === $eventName
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function registerEndpoint(array $attributes): WebhookEndpoint
    {
        $endpoint = new WebhookEndpoint;
        $endpoint->forceFill($attributes);

        return $endpoint;
    }

    public function disable(int $endpointId, ?string $reason = null): void {}

    public function enable(int $endpointId): void {}

    public function isEnabled(int $endpointId): bool
    {
        return true;
    }
}
