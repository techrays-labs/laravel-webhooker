<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Artisan command to prune old webhook events based on retention policy.
 */
class PruneCommand extends Command
{
    protected $signature = 'webhook:prune {--days= : Number of days to retain (overrides config)}';

    protected $description = 'Prune webhook events, dead-letter events, and health snapshots older than the configured retention period';

    public function handle(WebhookRepository $repository): int
    {
        $days = (int) ($this->option('days') ?: config('webhooks.retention_days', 30));

        $deleted = $repository->pruneEvents($days);
        $this->info("Pruned {$deleted} webhook event(s) older than {$days} day(s).");

        // Prune dead-letter events
        if (config('webhooks.dead_letter.enabled', false)) {
            $dlqDays = (int) config('webhooks.dead_letter.retention_days', 90);
            $dlqDeleted = $repository->pruneDeadLetterEvents($dlqDays);
            $this->info("Pruned {$dlqDeleted} dead-letter event(s) older than {$dlqDays} day(s).");
        }

        // Prune health snapshots
        if (config('webhooks.health_history.enabled', false)) {
            $healthDays = (int) config('webhooks.health_history.retention_days', 90);
            $healthDeleted = $repository->pruneHealthSnapshots($healthDays);
            $this->info("Pruned {$healthDeleted} health snapshot(s) older than {$healthDays} day(s).");
        }

        return self::SUCCESS;
    }
}
