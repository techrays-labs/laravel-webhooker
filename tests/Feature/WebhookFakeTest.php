<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Feature;

use TechraysLabs\Webhooker\Facades\Webhook;
use TechraysLabs\Webhooker\Testing\InteractsWithWebhooks;
use TechraysLabs\Webhooker\Tests\TestCase;
use TechraysLabs\Webhooker\Webhooker;

class WebhookFakeTest extends TestCase
{
    use InteractsWithWebhooks;

    public function test_facade_fake_replaces_service(): void
    {
        $fake = Webhook::fake();

        $this->assertInstanceOf(\TechraysLabs\Webhooker\Testing\WebhookFake::class, app(Webhooker::class));
    }

    public function test_trait_fake_replaces_service(): void
    {
        $fake = $this->fakeWebhooks();

        $fake->dispatch(1, 'order.created', ['id' => 123]);

        $fake->assertDispatched('order.created');
    }

    public function test_assert_dispatched(): void
    {
        $fake = Webhook::fake();

        Webhook::dispatch(1, 'order.created', ['id' => 123]);

        $fake->assertDispatched('order.created');
    }

    public function test_assert_dispatched_with_callback(): void
    {
        $fake = Webhook::fake();

        Webhook::dispatch(1, 'order.created', ['order_id' => 456]);

        $fake->assertDispatched('order.created', function ($event) {
            return $event->payload['order_id'] === 456;
        });
    }

    public function test_assert_nothing_dispatched(): void
    {
        $fake = Webhook::fake();

        $fake->assertNothingDispatched();
    }

    public function test_assert_dispatched_times(): void
    {
        $fake = Webhook::fake();

        Webhook::dispatch(1, 'order.created', ['id' => 1]);
        Webhook::dispatch(2, 'order.created', ['id' => 2]);
        Webhook::dispatch(3, 'order.created', ['id' => 3]);

        $fake->assertDispatchedTimes('order.created', 3);
    }

    public function test_dispatched_collection(): void
    {
        $fake = Webhook::fake();

        Webhook::dispatch(1, 'order.created', ['id' => 1]);
        Webhook::dispatch(2, 'user.updated', ['id' => 2]);
        Webhook::dispatch(3, 'order.created', ['id' => 3]);

        $this->assertCount(2, $fake->dispatched('order.created'));
        $this->assertCount(1, $fake->dispatched('user.updated'));
        $this->assertCount(0, $fake->dispatched('nonexistent'));
    }

    public function test_fake_does_not_touch_database(): void
    {
        Webhook::fake();

        Webhook::dispatch(1, 'order.created', ['id' => 123]);

        $this->assertDatabaseCount('webhook_events', 0);
    }
}
