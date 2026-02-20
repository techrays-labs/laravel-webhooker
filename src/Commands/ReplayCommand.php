<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechRaysLabs\Webhooker\Contracts\WebhookRepository;
use TechRaysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechRaysLabs\Webhooker\Jobs\ProcessInboundWebhookJob;
use TechRaysLabs\Webhooker\Models\WebhookEvent;

/**
 * Artisan command to replay a webhook event (outbound or inbound).
 */
class ReplayCommand extends Command
{
    protected $signature = 'webhook:replay {event_id : The ID of the webhook event to replay}';

    protected $description = 'Replay a webhook event by re-dispatching it to the queue';

    public function handle(WebhookRepository $repository): int
    {
        $eventId = (int) $this->argument('event_id');
        $event = $repository->findEvent($eventId);

        if ($event === null) {
            $this->error("Webhook event #{$eventId} not found.");

            return self::FAILURE;
        }

        $repository->updateEvent($event, [
            'status' => WebhookEvent::STATUS_PENDING,
            'next_retry_at' => null,
        ]);

        if ($event->endpoint && $event->endpoint->isInbound()) {
            ProcessInboundWebhookJob::dispatch($event->id);
            $this->info("Inbound webhook event #{$eventId} has been queued for reprocessing.");
        } else {
            DispatchWebhookJob::dispatch($event->id);
            $this->info("Outbound webhook event #{$eventId} has been queued for replay.");
        }

        return self::SUCCESS;
    }
}
