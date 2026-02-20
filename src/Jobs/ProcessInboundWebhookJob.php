<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use TechraysLabs\Webhooker\Contracts\InboundProcessor;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Events\InboundWebhookFailed;
use TechraysLabs\Webhooker\Events\InboundWebhookProcessed;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Queue job for processing an inbound webhook event asynchronously.
 */
class ProcessInboundWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $eventId,
    ) {
        $queueConfig = config('webhooks.queue', []);
        $this->onConnection($queueConfig['connection'] ?? null);
        $this->onQueue($queueConfig['name'] ?? 'webhooks');
    }

    public function handle(
        WebhookRepository $repository,
        InboundProcessor $processor,
    ): void {
        $event = $repository->findEvent($this->eventId);

        if ($event === null || $event->isDelivered()) {
            return;
        }

        $repository->updateEvent($event, ['status' => WebhookEvent::STATUS_PROCESSING]);

        try {
            $success = $processor->process($event);

            $repository->updateEvent($event, [
                'status' => $success ? WebhookEvent::STATUS_DELIVERED : WebhookEvent::STATUS_FAILED,
            ]);

            if ($success) {
                InboundWebhookProcessed::dispatch($event->fresh());
            } else {
                InboundWebhookFailed::dispatch($event->fresh());
            }
        } catch (\Throwable $e) {
            $repository->updateEvent($event, [
                'status' => WebhookEvent::STATUS_FAILED,
            ]);

            InboundWebhookFailed::dispatch($event->fresh(), $e);
        }
    }
}
