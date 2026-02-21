<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use Illuminate\Support\Facades\Event;
use TechraysLabs\Webhooker\Events\EndpointDisabled;
use TechraysLabs\Webhooker\Events\EndpointEnabled;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Tests\TestCase;
use TechraysLabs\Webhooker\Webhooker;

class EndpointEnableDisableTest extends TestCase
{
    private WebhookEndpoint $endpoint;

    private Webhooker $webhooker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhooker = app(Webhooker::class);
        $this->endpoint = WebhookEndpoint::create([
            'name' => 'Test Endpoint',
            'url' => 'https://example.com/webhook',
            'direction' => 'outbound',
            'secret' => 'test-secret',
            'is_active' => true,
            'timeout_seconds' => 30,
        ]);
    }

    public function test_disable_endpoint(): void
    {
        Event::fake();

        $this->webhooker->disable($this->endpoint->id, 'Maintenance');

        $this->endpoint->refresh();

        $this->assertFalse($this->endpoint->is_active);
        $this->assertNotNull($this->endpoint->disabled_at);
        $this->assertEquals('Maintenance', $this->endpoint->disabled_reason);
        $this->assertTrue($this->endpoint->isDisabled());

        Event::assertDispatched(EndpointDisabled::class, function ($event) {
            return $event->endpoint->id === $this->endpoint->id && $event->reason === 'Maintenance';
        });
    }

    public function test_disable_endpoint_without_reason(): void
    {
        $this->webhooker->disable($this->endpoint->id);

        $this->endpoint->refresh();

        $this->assertFalse($this->endpoint->is_active);
        $this->assertNotNull($this->endpoint->disabled_at);
        $this->assertNull($this->endpoint->disabled_reason);
    }

    public function test_enable_endpoint(): void
    {
        Event::fake();

        $this->webhooker->disable($this->endpoint->id, 'Maintenance');
        $this->webhooker->enable($this->endpoint->id);

        $this->endpoint->refresh();

        $this->assertTrue($this->endpoint->is_active);
        $this->assertNull($this->endpoint->disabled_at);
        $this->assertNull($this->endpoint->disabled_reason);
        $this->assertFalse($this->endpoint->isDisabled());

        Event::assertDispatched(EndpointEnabled::class, function ($event) {
            return $event->endpoint->id === $this->endpoint->id;
        });
    }

    public function test_is_enabled_check(): void
    {
        $this->assertTrue($this->webhooker->isEnabled($this->endpoint->id));

        $this->webhooker->disable($this->endpoint->id);

        $this->assertFalse($this->webhooker->isEnabled($this->endpoint->id));

        $this->webhooker->enable($this->endpoint->id);

        $this->assertTrue($this->webhooker->isEnabled($this->endpoint->id));
    }

    public function test_is_enabled_returns_false_for_nonexistent_endpoint(): void
    {
        $this->assertFalse($this->webhooker->isEnabled(9999));
    }

    public function test_disable_command(): void
    {
        $this->artisan('webhook:endpoint:disable', [
            'id' => $this->endpoint->id,
            '--reason' => 'Under maintenance',
        ])->expectsOutput("Endpoint #{$this->endpoint->id} has been disabled.")
            ->assertExitCode(0);

        $this->endpoint->refresh();
        $this->assertFalse($this->endpoint->is_active);
        $this->assertEquals('Under maintenance', $this->endpoint->disabled_reason);
    }

    public function test_enable_command(): void
    {
        $this->webhooker->disable($this->endpoint->id, 'Test');

        $this->artisan('webhook:endpoint:enable', ['id' => $this->endpoint->id])
            ->expectsOutput("Endpoint #{$this->endpoint->id} has been enabled.")
            ->assertExitCode(0);

        $this->endpoint->refresh();
        $this->assertTrue($this->endpoint->is_active);
        $this->assertNull($this->endpoint->disabled_at);
    }

    public function test_is_disabled_returns_false_when_not_disabled(): void
    {
        $this->assertFalse($this->endpoint->isDisabled());
    }
}
