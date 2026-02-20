<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Tests\Unit;

use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Tests\TestCase;

class WebhookEventModelTest extends TestCase
{
    public function test_status_helper_methods(): void
    {
        $event = new WebhookEvent(['status' => WebhookEvent::STATUS_PENDING]);
        $this->assertTrue($event->isPending());
        $this->assertFalse($event->isDelivered());
        $this->assertFalse($event->isFailed());

        $event->status = WebhookEvent::STATUS_DELIVERED;
        $this->assertTrue($event->isDelivered());
        $this->assertFalse($event->isPending());

        $event->status = WebhookEvent::STATUS_FAILED;
        $this->assertTrue($event->isFailed());
    }

    public function test_payload_is_cast_to_array(): void
    {
        $event = new WebhookEvent([
            'payload' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $this->assertIsArray($event->payload);
        $this->assertEquals('value', $event->payload['key']);
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals('pending', WebhookEvent::STATUS_PENDING);
        $this->assertEquals('processing', WebhookEvent::STATUS_PROCESSING);
        $this->assertEquals('delivered', WebhookEvent::STATUS_DELIVERED);
        $this->assertEquals('failed', WebhookEvent::STATUS_FAILED);
    }
}
