<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use Illuminate\Support\Facades\Cache;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Enums\CircuitState;
use TechraysLabs\Webhooker\Events\EndpointCircuitClosed;
use TechraysLabs\Webhooker\Events\EndpointCircuitOpened;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;

/**
 * Cache-based circuit breaker implementation.
 *
 * Stores circuit state in Laravel's cache for speed (not database).
 */
class CacheCircuitBreaker implements CircuitBreaker
{
    public function isAvailable(WebhookEndpoint $endpoint): bool
    {
        if (! config('webhooks.circuit_breaker.enabled', true)) {
            return true;
        }

        $state = $this->getState($endpoint);

        if ($state === CircuitState::CLOSED) {
            return true;
        }

        if ($state === CircuitState::OPEN) {
            $cooldown = (int) config('webhooks.circuit_breaker.cooldown_seconds', 300);
            $openedAt = (int) Cache::get($this->cacheKey($endpoint, 'opened_at'), 0);

            if (time() - $openedAt >= $cooldown) {
                $this->setState($endpoint, CircuitState::HALF_OPEN);
                Cache::put($this->cacheKey($endpoint, 'half_open_successes'), 0);

                return true;
            }

            return false;
        }

        // HALF_OPEN — allow delivery
        return true;
    }

    public function recordSuccess(WebhookEndpoint $endpoint): void
    {
        if (! config('webhooks.circuit_breaker.enabled', true)) {
            return;
        }

        $state = $this->getState($endpoint);

        if ($state === CircuitState::HALF_OPEN) {
            $successThreshold = (int) config('webhooks.circuit_breaker.success_threshold', 2);
            $successes = (int) Cache::increment($this->cacheKey($endpoint, 'half_open_successes'));

            if ($successes >= $successThreshold) {
                $this->reset($endpoint);
                EndpointCircuitClosed::dispatch($endpoint);
            }

            return;
        }

        // In CLOSED state, reset failure count on success
        if ($state === CircuitState::CLOSED) {
            Cache::put($this->cacheKey($endpoint, 'failures'), 0);
        }
    }

    public function recordFailure(WebhookEndpoint $endpoint): void
    {
        if (! config('webhooks.circuit_breaker.enabled', true)) {
            return;
        }

        $state = $this->getState($endpoint);

        if ($state === CircuitState::HALF_OPEN) {
            // Failed during half-open probe — go back to OPEN
            $this->setState($endpoint, CircuitState::OPEN);
            Cache::put($this->cacheKey($endpoint, 'opened_at'), time());
            Cache::forget($this->cacheKey($endpoint, 'half_open_successes'));

            return;
        }

        $failureThreshold = (int) config('webhooks.circuit_breaker.failure_threshold', 10);
        $failures = (int) Cache::increment($this->cacheKey($endpoint, 'failures'));

        if ($failures >= $failureThreshold && $state === CircuitState::CLOSED) {
            $this->setState($endpoint, CircuitState::OPEN);
            Cache::put($this->cacheKey($endpoint, 'opened_at'), time());
            EndpointCircuitOpened::dispatch($endpoint, $failures);
        }
    }

    public function getState(WebhookEndpoint $endpoint): CircuitState
    {
        if (! config('webhooks.circuit_breaker.enabled', true)) {
            return CircuitState::CLOSED;
        }

        $state = Cache::get($this->cacheKey($endpoint, 'state'));

        if ($state === null) {
            return CircuitState::CLOSED;
        }

        return CircuitState::from($state);
    }

    public function reset(WebhookEndpoint $endpoint): void
    {
        Cache::forget($this->cacheKey($endpoint, 'state'));
        Cache::forget($this->cacheKey($endpoint, 'failures'));
        Cache::forget($this->cacheKey($endpoint, 'opened_at'));
        Cache::forget($this->cacheKey($endpoint, 'half_open_successes'));
    }

    private function setState(WebhookEndpoint $endpoint, CircuitState $state): void
    {
        Cache::put($this->cacheKey($endpoint, 'state'), $state->value);
    }

    private function cacheKey(WebhookEndpoint $endpoint, string $suffix): string
    {
        return "webhooker:circuit:{$endpoint->id}:{$suffix}";
    }
}
