<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Storage;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Manages table partitioning for high-volume webhook tables.
 *
 * Supports MySQL RANGE partitioning and PostgreSQL declarative partitioning.
 * SQLite is not supported for partitioning.
 */
class PartitionManager
{
    /**
     * Create partitions for a table from the start date to the end date.
     */
    public function createPartitions(string $table, Carbon $from, Carbon $to, string $strategy = 'monthly'): void
    {
        $driver = $this->getDatabaseDriver();
        $current = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();

        while ($current->lte($end)) {
            $partitionName = $this->getPartitionName($table, $current, $strategy);
            $boundary = $this->getNextBoundary($current, $strategy);

            if ($driver === 'mysql') {
                $this->createMysqlPartition($table, $partitionName, $boundary);
            } elseif ($driver === 'pgsql') {
                $this->createPostgresPartition($table, $partitionName, $current, $boundary);
            }

            $current = $boundary->copy();
        }
    }

    /**
     * Drop partitions older than the given date.
     *
     * @return int Number of partitions dropped
     */
    public function dropPartitions(string $table, Carbon $before): int
    {
        $driver = $this->getDatabaseDriver();
        $partitions = $this->listPartitions($table);
        $dropped = 0;

        foreach ($partitions as $partition) {
            $partitionDate = $this->parsePartitionDate($partition['name']);
            if ($partitionDate !== null && $partitionDate->lt($before)) {
                if ($driver === 'mysql') {
                    DB::statement("ALTER TABLE `{$table}` DROP PARTITION `{$partition['name']}`");
                } elseif ($driver === 'pgsql') {
                    DB::statement("DROP TABLE IF EXISTS \"{$partition['name']}\"");
                }
                $dropped++;
            }
        }

        return $dropped;
    }

    /**
     * List existing partitions for a table.
     *
     * @return array<int, array{name: string, rows: int}>
     */
    public function listPartitions(string $table): array
    {
        $driver = $this->getDatabaseDriver();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $results = DB::select(
                'SELECT PARTITION_NAME as name, TABLE_ROWS as `rows` FROM INFORMATION_SCHEMA.PARTITIONS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
                [$database, $table]
            );

            return array_map(fn ($r) => ['name' => $r->name, 'rows' => (int) $r->rows], $results);
        }

        if ($driver === 'pgsql') {
            $results = DB::select(
                "SELECT inhrelid::regclass::text as name FROM pg_inherits WHERE inhparent = ?::regclass",
                [$table]
            );

            return array_map(fn ($r) => ['name' => $r->name, 'rows' => 0], $results);
        }

        return [];
    }

    private function getDatabaseDriver(): string
    {
        $connection = config('webhooks.storage.drivers.eloquent.connection');

        return DB::connection($connection)->getDriverName();
    }

    private function getPartitionName(string $table, Carbon $date, string $strategy): string
    {
        return match ($strategy) {
            'daily' => "{$table}_p{$date->format('Ymd')}",
            'weekly' => "{$table}_p{$date->format('Y')}w{$date->format('W')}",
            default => "{$table}_p{$date->format('Ym')}",
        };
    }

    private function getNextBoundary(Carbon $current, string $strategy): Carbon
    {
        return match ($strategy) {
            'daily' => $current->copy()->addDay()->startOfDay(),
            'weekly' => $current->copy()->addWeek()->startOfWeek(),
            default => $current->copy()->addMonth()->startOfMonth(),
        };
    }

    private function createMysqlPartition(string $table, string $partitionName, Carbon $boundary): void
    {
        $value = $boundary->format('Y-m-d');
        DB::statement("ALTER TABLE `{$table}` ADD PARTITION (PARTITION `{$partitionName}` VALUES LESS THAN ('{$value}'))");
    }

    private function createPostgresPartition(string $table, string $partitionName, Carbon $from, Carbon $to): void
    {
        $fromStr = $from->format('Y-m-d');
        $toStr = $to->format('Y-m-d');
        DB::statement("CREATE TABLE IF NOT EXISTS \"{$partitionName}\" PARTITION OF \"{$table}\" FOR VALUES FROM ('{$fromStr}') TO ('{$toStr}')");
    }

    private function parsePartitionDate(string $partitionName): ?Carbon
    {
        if (preg_match('/_p(\d{6})$/', $partitionName, $matches)) {
            return Carbon::createFromFormat('Ym', $matches[1])?->startOfMonth();
        }
        if (preg_match('/_p(\d{8})$/', $partitionName, $matches)) {
            return Carbon::createFromFormat('Ymd', $matches[1]);
        }

        return null;
    }
}
