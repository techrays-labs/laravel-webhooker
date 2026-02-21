<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Enums\CircuitState;
use TechraysLabs\Webhooker\Events\EndpointCircuitClosed;
use TechraysLabs\Webhooker\Events\EndpointCircuitOpened;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;

    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->circuitBreaker = app(CircuitBreaker::class);
        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Test Endpoint',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_initial_state_is_closed(): void
    {
        $this->assertEquals(CircuitState::CLOSED, $this->circuitBreaker->getState($this->endpoint));
    }

    public function test_endpoint_is_available_when_closed(): void
    {
        $this->assertTrue($this->circuitBreaker->isAvailable($this->endpoint));
    }

    public function test_circuit_opens_after_failure_threshold(): void
    {
        Event::fake();

        config(['webhooks.circuit_breaker.failure_threshold' => 3]);

        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->assertEquals(CircuitState::CLOSED, $this->circuitBreaker->getState($this->endpoint));

        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->assertEquals(CircuitState::OPEN, $this->circuitBreaker->getState($this->endpoint));

        Event::assertDispatched(EndpointCircuitOpened::class, function ($event) {
            return $event->endpoint->id === $this->endpoint->id && $event->failureCount === 3;
        });
    }

    public function test_endpoint_is_not_available_when_open(): void
    {
        config(['webhooks.circuit_breaker.failure_threshold' => 1]);

        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->assertFalse($this->circuitBreaker->isAvailable($this->endpoint));
    }

    public function test_circuit_transitions_to_half_open_after_cooldown(): void
    {
        config([
            'webhooks.circuit_breaker.failure_threshold' => 1,
            'webhooks.circuit_breaker.cooldown_seconds' => 5,
        ]);

        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->assertEquals(CircuitState::OPEN, $this->circuitBreaker->getState($this->endpoint));

        // Simulate cooldown passed by setting opened_at in the past
        Cache::put("webhooker:circuit:{$this->endpoint->id}:opened_at", time() - 10);

        $this->assertTrue($this->circuitBreaker->isAvailable($this->endpoint));
        $this->assertEquals(CircuitState::HALF_OPEN, $this->circuitBreaker->getState($this->endpoint));
    }

    public function test_circuit_closes_after_success_threshold_in_half_open(): void
    {
        Event::fake();

        config([
            'webhooks.circuit_breaker.failure_threshold' => 1,
            'webhooks.circuit_breaker.cooldown_seconds' => 0,
            'webhooks.circuit_breaker.success_threshold' => 2,
        ]);

        // Trip the circuit
        $this->circuitBreaker->recordFailure($this->endpoint);

        // Transition to half-open
        $this->circuitBreaker->isAvailable($this->endpoint);

        // Record successes
        $this->circuitBreaker->recordSuccess($this->endpoint);
        $this->circuitBreaker->recordSuccess($this->endpoint);

        $this->assertEquals(CircuitState::CLOSED, $this->circuitBreaker->getState($this->endpoint));

        Event::assertDispatched(EndpointCircuitClosed::class, function ($event) {
            return $event->endpoint->id === $this->endpoint->id;
        });
    }

    public function test_failure_in_half_open_returns_to_open(): void
    {
        config([
            'webhooks.circuit_breaker.failure_threshold' => 1,
            'webhooks.circuit_breaker.cooldown_seconds' => 0,
        ]);

        // Trip the circuit
        $this->circuitBreaker->recordFailure($this->endpoint);

        // Transition to half-open
        $this->circuitBreaker->isAvailable($this->endpoint);
        $this->assertEquals(CircuitState::HALF_OPEN, $this->circuitBreaker->getState($this->endpoint));

        // Fail in half-open
        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->assertEquals(CircuitState::OPEN, $this->circuitBreaker->getState($this->endpoint));
    }

    public function test_reset_clears_circuit_state(): void
    {
        config(['webhooks.circuit_breaker.failure_threshold' => 1]);

        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->assertEquals(CircuitState::OPEN, $this->circuitBreaker->getState($this->endpoint));

        $this->circuitBreaker->reset($this->endpoint);
        $this->assertEquals(CircuitState::CLOSED, $this->circuitBreaker->getState($this->endpoint));
        $this->assertTrue($this->circuitBreaker->isAvailable($this->endpoint));
    }

    public function test_success_resets_failure_count_in_closed_state(): void
    {
        config(['webhooks.circuit_breaker.failure_threshold' => 3]);

        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->circuitBreaker->recordSuccess($this->endpoint);

        // Third failure should NOT trip circuit since counter was reset
        $this->circuitBreaker->recordFailure($this->endpoint);
        $this->assertEquals(CircuitState::CLOSED, $this->circuitBreaker->getState($this->endpoint));
    }

    public function test_circuit_breaker_disabled_always_returns_available(): void
    {
        config(['webhooks.circuit_breaker.enabled' => false]);

        $circuitBreaker = app(CircuitBreaker::class);

        // Record many failures
        for ($i = 0; $i < 20; $i++) {
            $circuitBreaker->recordFailure($this->endpoint);
        }

        $this->assertTrue($circuitBreaker->isAvailable($this->endpoint));
        $this->assertEquals(CircuitState::CLOSED, $circuitBreaker->getState($this->endpoint));
    }
}
