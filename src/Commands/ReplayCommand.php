<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Events\WebhookReplayRequested;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Jobs\ProcessInboundWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Artisan command to replay webhook events (single or bulk).
 */
class ReplayCommand extends Command
{
    protected $signature = 'webhook:replay
        {event_id? : The ID of a single webhook event to replay}
        {--status= : Filter events by status for bulk replay (e.g. failed)}
        {--endpoint= : Filter events by endpoint route_token for bulk replay}
        {--force : Bypass circuit breaker check}';

    protected $description = 'Replay webhook events by re-dispatching them to the queue';

    public function handle(WebhookRepository $repository): int
    {
        $eventId = $this->argument('event_id');
        $statusFilter = $this->option('status');
        $endpointFilter = $this->option('endpoint');
        $force = (bool) $this->option('force');

        if ($eventId !== null) {
            return $this->replaySingle($repository, (int) $eventId, $force);
        }

        if ($statusFilter !== null || $endpointFilter !== null) {
            return $this->replayBulk($repository, $statusFilter, $endpointFilter, $force);
        }

        $this->error('Provide an event_id or use --status/--endpoint flags for bulk replay.');

        return self::FAILURE;
    }

    private function replaySingle(WebhookRepository $repository, int $eventId, bool $force): int
    {
        $event = $repository->findEvent($eventId);

        if ($event === null) {
            $this->error("Webhook event #{$eventId} not found.");

            return self::FAILURE;
        }

        $this->dispatchReplay($repository, $event, $force);

        if ($event->endpoint && $event->endpoint->isInbound()) {
            $this->info("Inbound webhook event #{$eventId} has been queued for reprocessing.");
        } else {
            $this->info("Outbound webhook event #{$eventId} has been queued for replay.");
        }

        return self::SUCCESS;
    }

    private function replayBulk(WebhookRepository $repository, ?string $status, ?string $endpointToken, bool $force): int
    {
        $query = WebhookEvent::query();

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($endpointToken !== null) {
            $endpoint = WebhookEndpoint::where('route_token', $endpointToken)->first();

            if ($endpoint === null) {
                $this->error("Endpoint with token '{$endpointToken}' not found.");

                return self::FAILURE;
            }

            $query->where('endpoint_id', $endpoint->id);
        }

        $maxBatch = (int) config('webhooks.dashboard.max_bulk_size', 100);
        $events = $query->limit($maxBatch)->get();

        if ($events->isEmpty()) {
            $this->info('No matching events found.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($events as $event) {
            $this->dispatchReplay($repository, $event, $force);
            $count++;
        }

        $this->info("{$count} event(s) queued for replay.");

        return self::SUCCESS;
    }

    private function dispatchReplay(WebhookRepository $repository, WebhookEvent $event, bool $force): void
    {
        $repository->updateEvent($event, [
            'status' => WebhookEvent::STATUS_PENDING,
            'next_retry_at' => null,
        ]);

        WebhookReplayRequested::dispatch($event);

        if ($event->endpoint && $event->endpoint->isInbound()) {
            ProcessInboundWebhookJob::dispatch($event->id);
        } else {
            DispatchWebhookJob::dispatch($event->id, $force);
        }
    }
}
