<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Contracts;

use TechraysLabs\Webhooker\Enums\CircuitState;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;

/**
 * Contract for the circuit breaker pattern applied to webhook endpoints.
 *
 * The circuit breaker prevents wasting queue resources on endpoints
 * that are consistently failing.
 */
interface CircuitBreaker
{
    /**
     * Determine if the endpoint is available for delivery.
     */
    public function isAvailable(WebhookEndpoint $endpoint): bool;

    /**
     * Record a successful delivery to the endpoint.
     */
    public function recordSuccess(WebhookEndpoint $endpoint): void;

    /**
     * Record a failed delivery to the endpoint.
     */
    public function recordFailure(WebhookEndpoint $endpoint): void;

    /**
     * Get the current circuit state for the endpoint.
     */
    public function getState(WebhookEndpoint $endpoint): CircuitState;

    /**
     * Reset the circuit breaker for the endpoint back to CLOSED.
     */
    public function reset(WebhookEndpoint $endpoint): void;
}
