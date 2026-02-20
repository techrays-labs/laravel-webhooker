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

    protected $description = 'Prune webhook events older than the configured retention period';

    public function handle(WebhookRepository $repository): int
    {
        $days = (int) ($this->option('days') ?: config('webhooks.retention_days', 30));

        $deleted = $repository->pruneEvents($days);

        $this->info("Pruned {$deleted} webhook event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
