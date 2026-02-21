<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Testing;

use TechraysLabs\Webhooker\Webhooker;

/**
 * Test trait that provides webhook faking capabilities.
 *
 * Usage:
 *   use InteractsWithWebhooks;
 *
 *   public function test_something(): void {
 *       $fake = $this->fakeWebhooks();
 *       // ... trigger your app code ...
 *       $fake->assertDispatched('order.created');
 *   }
 */
trait InteractsWithWebhooks
{
    /**
     * Replace the Webhooker service with a fake for testing.
     */
    protected function fakeWebhooks(): WebhookFake
    {
        $fake = new WebhookFake;

        app()->instance(Webhooker::class, $fake);

        return $fake;
    }
}
