<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Enums\CircuitState;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;

class CircuitBreakerCommandTest extends TestCase
{
    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Test Endpoint',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_circuit_status_command_shows_endpoint_states(): void
    {
        $this->artisan('webhook:circuit:status')
            ->expectsTable(
                ['Token', 'Name', 'Circuit State', 'Available'],
                [[$this->endpoint->route_token, 'Test Endpoint', 'CLOSED', 'Yes']],
            )
            ->assertExitCode(0);
    }

    public function test_circuit_status_command_with_no_endpoints(): void
    {
        $this->endpoint->update(['direction' => 'inbound']);

        $this->artisan('webhook:circuit:status')
            ->expectsOutput('No outbound endpoints found.')
            ->assertExitCode(0);
    }

    public function test_circuit_reset_command(): void
    {
        $circuitBreaker = app(CircuitBreaker::class);

        config(['webhooks.circuit_breaker.failure_threshold' => 1]);
        $circuitBreaker->recordFailure($this->endpoint);
        $this->assertEquals(CircuitState::OPEN, $circuitBreaker->getState($this->endpoint));

        $this->artisan('webhook:circuit:reset', ['endpoint_id' => $this->endpoint->id])
            ->expectsOutputToContain('has been reset from open to closed')
            ->assertExitCode(0);

        $this->assertEquals(CircuitState::CLOSED, $circuitBreaker->getState($this->endpoint));
    }

    public function test_circuit_reset_command_with_nonexistent_endpoint(): void
    {
        $this->artisan('webhook:circuit:reset', ['endpoint_id' => 9999])
            ->expectsOutput('Endpoint #9999 not found.')
            ->assertExitCode(1);
    }

    public function test_circuit_status_shows_open_state(): void
    {
        $circuitBreaker = app(CircuitBreaker::class);

        config(['webhooks.circuit_breaker.failure_threshold' => 1]);
        $circuitBreaker->recordFailure($this->endpoint);

        $this->artisan('webhook:circuit:status')
            ->expectsTable(
                ['Token', 'Name', 'Circuit State', 'Available'],
                [[$this->endpoint->route_token, 'Test Endpoint', 'OPEN', 'No']],
            )
            ->assertExitCode(0);
    }
}
