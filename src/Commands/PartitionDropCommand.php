<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use TechraysLabs\Webhooker\Storage\PartitionManager;

/**
 * Artisan command to drop old table partitions.
 */
class PartitionDropCommand extends Command
{
    protected $signature = 'webhook:partition:drop
        {--before= : Drop partitions before this date (Y-m)}';

    protected $description = 'Drop old table partitions for webhook tables';

    public function handle(PartitionManager $partitionManager): int
    {
        if (! config('webhooks.partitioning.enabled', false)) {
            $this->error('Table partitioning is not enabled. Set webhooks.partitioning.enabled to true.');

            return self::FAILURE;
        }

        $before = $this->option('before');
        if ($before === null) {
            $retentionDays = (int) config('webhooks.retention_days', 30);
            $beforeDate = Carbon::now()->subDays($retentionDays);
        } else {
            $beforeDate = Carbon::createFromFormat('Y-m', $before)?->startOfMonth();
            if ($beforeDate === null) {
                $this->error('Invalid date format. Use Y-m (e.g., 2024-06).');

                return self::FAILURE;
            }
        }

        $tables = config('webhooks.partitioning.tables', ['webhook_events', 'webhook_attempts']);
        $totalDropped = 0;

        foreach ($tables as $table) {
            $dropped = $partitionManager->dropPartitions($table, $beforeDate);
            $totalDropped += $dropped;
            $this->info("Dropped {$dropped} partition(s) from '{$table}'.");
        }

        $this->info("Total: {$totalDropped} partition(s) dropped.");

        return self::SUCCESS;
    }
}
