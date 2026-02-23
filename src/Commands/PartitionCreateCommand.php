<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use TechraysLabs\Webhooker\Storage\PartitionManager;

/**
 * Artisan command to create future table partitions.
 */
class PartitionCreateCommand extends Command
{
    protected $signature = 'webhook:partition:create
        {--months=3 : Number of future partitions to create}';

    protected $description = 'Create future table partitions for webhook tables';

    public function handle(PartitionManager $partitionManager): int
    {
        if (! config('webhooks.partitioning.enabled', false)) {
            $this->error('Table partitioning is not enabled. Set webhooks.partitioning.enabled to true.');

            return self::FAILURE;
        }

        $months = (int) $this->option('months');
        $tables = config('webhooks.partitioning.tables', ['webhook_events', 'webhook_attempts']);
        $strategy = config('webhooks.partitioning.strategy', 'monthly');

        foreach ($tables as $table) {
            $partitionManager->createPartitions(
                $table,
                Carbon::now(),
                Carbon::now()->addMonths($months),
                $strategy,
            );
            $this->info("Created partitions for '{$table}' ({$months} months ahead).");
        }

        return self::SUCCESS;
    }
}
