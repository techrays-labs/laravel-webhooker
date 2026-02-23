<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Artisan command for managing the dead-letter queue.
 */
class DeadLetterCommand extends Command
{
    protected $signature = 'webhook:dead-letter
        {action=list : Action to perform (list, retry, purge, count)}
        {--id= : Specific event ID for retry}
        {--endpoint= : Filter by endpoint route_token}
        {--days= : Override retention days for purge}';

    protected $description = 'Manage dead-letter webhook events';

    public function handle(WebhookRepository $repository): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listDeadLetters($repository),
            'retry' => $this->retryDeadLetter($repository),
            'purge' => $this->purgeDeadLetters($repository),
            'count' => $this->countDeadLetters($repository),
            default => $this->invalidAction($action),
        };
    }

    private function listDeadLetters(WebhookRepository $repository): int
    {
        $filters = [];
        $endpointToken = $this->option('endpoint');

        if ($endpointToken) {
            $endpoint = $repository->findEndpointByRouteToken($endpointToken);
            if ($endpoint === null) {
                $this->error("Endpoint with token '{$endpointToken}' not found.");

                return self::FAILURE;
            }
            $filters['endpoint_id'] = $endpoint->id;
        }

        $events = $repository->paginateDeadLetterEvents($filters, 20);

        if ($events->isEmpty()) {
            $this->info('No dead-letter events found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($events as $event) {
            $rows[] = [
                $event->id,
                $event->event_name,
                $event->endpoint?->route_token ?? 'N/A',
                $event->dead_letter_reason ?? 'N/A',
                $event->dead_lettered_at?->toDateTimeString() ?? 'N/A',
            ];
        }

        $this->table(['ID', 'Event', 'Endpoint', 'Reason', 'Dead-Lettered At'], $rows);
        $this->info("Total: {$events->total()} dead-letter event(s).");

        return self::SUCCESS;
    }

    private function retryDeadLetter(WebhookRepository $repository): int
    {
        $eventId = $this->option('id');

        if ($eventId === null) {
            $this->error('The --id option is required for retry.');

            return self::FAILURE;
        }

        $event = $repository->findEvent((int) $eventId);

        if ($event === null) {
            $this->error("Event #{$eventId} not found.");

            return self::FAILURE;
        }

        if ($event->status !== WebhookEvent::STATUS_DEAD_LETTER) {
            $this->error("Event #{$eventId} is not in the dead-letter queue.");

            return self::FAILURE;
        }

        $repository->restoreFromDeadLetter($event);
        DispatchWebhookJob::dispatch($event->id);

        $this->info("Event #{$eventId} restored from dead-letter queue and queued for retry.");

        return self::SUCCESS;
    }

    private function purgeDeadLetters(WebhookRepository $repository): int
    {
        $days = (int) ($this->option('days') ?: config('webhooks.dead_letter.retention_days', 90));

        $deleted = $repository->pruneDeadLetterEvents($days);

        $this->info("Purged {$deleted} dead-letter event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }

    private function countDeadLetters(WebhookRepository $repository): int
    {
        $count = $repository->countDeadLetterEvents();

        $this->info("Dead-letter queue contains {$count} event(s).");

        return self::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Unknown action: {$action}. Valid actions: list, retry, purge, count.");

        return self::FAILURE;
    }
}
