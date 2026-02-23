<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Models\WebhookAttempt;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEndpointTag;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Eloquent-based implementation of the WebhookRepository contract.
 *
 * Supports optional database connection and read-replica configuration
 * for multi-database deployments.
 */
class EloquentWebhookRepository implements WebhookRepository
{
    public function __construct(
        private readonly ?string $connection = null,
        private readonly ?string $readConnection = null,
    ) {}

    // Endpoints

    public function createEndpoint(array $attributes): WebhookEndpoint
    {
        if ($this->connection) {
            return WebhookEndpoint::on($this->connection)->create($attributes);
        }

        return WebhookEndpoint::create($attributes);
    }

    public function findEndpoint(int $id): ?WebhookEndpoint
    {
        return $this->readQuery(WebhookEndpoint::class)->find($id);
    }

    public function findEndpointByRouteToken(string $routeToken): ?WebhookEndpoint
    {
        return $this->readQuery(WebhookEndpoint::class)->where('route_token', $routeToken)->first();
    }

    /**
     * @return Collection<int, WebhookEndpoint>
     */
    public function getActiveEndpoints(?string $direction = null): Collection
    {
        $query = $this->readQuery(WebhookEndpoint::class)->where('is_active', true);

        if ($direction !== null) {
            $query->where('direction', $direction);
        }

        return $query->get();
    }

    /**
     * @return LengthAwarePaginator<int, WebhookEndpoint>
     */
    public function paginateEndpoints(int $perPage = 15): LengthAwarePaginator
    {
        return $this->readQuery(WebhookEndpoint::class)->orderByDesc('created_at')->paginate($perPage);
    }

    public function updateEndpoint(WebhookEndpoint $endpoint, array $attributes): bool
    {
        return $endpoint->update($attributes);
    }

    public function cleanExpiredSecrets(int $gracePeriodHours): int
    {
        $cutoff = Carbon::now()->subHours($gracePeriodHours);

        return $this->writeQuery(WebhookEndpoint::class)
            ->whereNotNull('previous_secret')
            ->whereNotNull('secret_rotated_at')
            ->where('secret_rotated_at', '<', $cutoff)
            ->update([
                'previous_secret' => null,
                'secret_rotated_at' => null,
            ]);
    }

    // Events

    public function createEvent(array $attributes): WebhookEvent
    {
        if ($this->connection) {
            return WebhookEvent::on($this->connection)->create($attributes);
        }

        return WebhookEvent::create($attributes);
    }

    public function findEvent(int $id): ?WebhookEvent
    {
        return $this->readQuery(WebhookEvent::class)->with('endpoint')->find($id);
    }

    public function findEventByIdempotencyKey(string $key): ?WebhookEvent
    {
        return $this->readQuery(WebhookEvent::class)->where('idempotency_key', $key)->first();
    }

    public function getLastFailedEvent(): ?WebhookEvent
    {
        return $this->readQuery(WebhookEvent::class)
            ->where('status', WebhookEvent::STATUS_FAILED)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Collection<int, WebhookEvent>
     */
    public function getRetryableEvents(): Collection
    {
        return $this->readQuery(WebhookEvent::class)
            ->where('status', WebhookEvent::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', Carbon::now());
            })
            ->get();
    }

    public function updateEvent(WebhookEvent $event, array $attributes): bool
    {
        return $event->update($attributes);
    }

    /**
     * @return LengthAwarePaginator<int, WebhookEvent>
     */
    public function paginateEvents(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->readQuery(WebhookEvent::class)->with('endpoint')->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['endpoint_id'])) {
            $query->where('endpoint_id', $filters['endpoint_id']);
        }

        if (! empty($filters['event_name'])) {
            $query->where('event_name', 'like', '%'.$filters['event_name'].'%');
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('endpoint.tags', function ($q) use ($filters) {
                $q->where('tag', $filters['tag']);
            });
        }

        return $query->paginate($perPage);
    }

    public function deleteEvents(array $eventIds): int
    {
        return $this->writeQuery(WebhookEvent::class)->whereIn('id', $eventIds)->delete();
    }

    /**
     * @return LengthAwarePaginator<int, WebhookEvent>
     */
    public function getRecentEventsForEndpoint(int $endpointId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->readQuery(WebhookEvent::class)
            ->where('endpoint_id', $endpointId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, array{date: string, total: int, success: int}>
     */
    public function getEventCountsForEndpointByDay(int $endpointId, Carbon $start, Carbon $end): Collection
    {
        $results = collect();
        $current = $start->copy()->startOfDay();
        $endDay = $end->copy()->endOfDay();

        while ($current->lte($endDay)) {
            $dayStart = $current->copy()->startOfDay();
            $dayEnd = $current->copy()->endOfDay();

            $total = $this->readQuery(WebhookEvent::class)
                ->where('endpoint_id', $endpointId)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $success = $this->readQuery(WebhookEvent::class)
                ->where('endpoint_id', $endpointId)
                ->where('status', WebhookEvent::STATUS_DELIVERED)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $results->push([
                'date' => $dayStart->format('M d'),
                'total' => $total,
                'success' => $success,
            ]);

            $current->addDay();
        }

        return $results;
    }

    /**
     * @return Collection<int, WebhookEvent>
     */
    public function getFilteredEvents(array $filters = [], int $limit = 100): Collection
    {
        $query = $this->readQuery(WebhookEvent::class);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['endpoint_id'])) {
            $query->where('endpoint_id', $filters['endpoint_id']);
        }

        return $query->limit($limit)->get();
    }

    public function pruneEvents(int $days): int
    {
        if (config('webhooks.partitioning.enabled', false)) {
            return app(\TechraysLabs\Webhooker\Storage\PartitionManager::class)
                ->dropPartitions('webhook_events', Carbon::now()->subDays($days));
        }

        $cutoff = Carbon::now()->subDays($days);

        return $this->writeQuery(WebhookEvent::class)->where('created_at', '<', $cutoff)->delete();
    }

    // Dead-Letter Queue

    /**
     * @return LengthAwarePaginator<int, WebhookEvent>
     */
    public function paginateDeadLetterEvents(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->readQuery(WebhookEvent::class)
            ->with('endpoint')
            ->where('status', WebhookEvent::STATUS_DEAD_LETTER)
            ->orderByDesc('dead_lettered_at');

        if (! empty($filters['endpoint_id'])) {
            $query->where('endpoint_id', $filters['endpoint_id']);
        }

        return $query->paginate($perPage);
    }

    public function moveToDeadLetter(WebhookEvent $event, string $reason): bool
    {
        return $event->update([
            'status' => WebhookEvent::STATUS_DEAD_LETTER,
            'dead_letter_reason' => $reason,
            'dead_lettered_at' => Carbon::now(),
        ]);
    }

    public function restoreFromDeadLetter(WebhookEvent $event): bool
    {
        return $event->update([
            'status' => WebhookEvent::STATUS_PENDING,
            'dead_letter_reason' => null,
            'dead_lettered_at' => null,
            'next_retry_at' => null,
        ]);
    }

    public function pruneDeadLetterEvents(int $days): int
    {
        $cutoff = Carbon::now()->subDays($days);

        return $this->writeQuery(WebhookEvent::class)
            ->where('status', WebhookEvent::STATUS_DEAD_LETTER)
            ->where('dead_lettered_at', '<', $cutoff)
            ->delete();
    }

    public function countDeadLetterEvents(): int
    {
        return $this->readQuery(WebhookEvent::class)
            ->where('status', WebhookEvent::STATUS_DEAD_LETTER)
            ->count();
    }

    // Batches

    public function createBatch(array $attributes): \TechraysLabs\Webhooker\Models\WebhookBatch
    {
        if ($this->connection) {
            return \TechraysLabs\Webhooker\Models\WebhookBatch::on($this->connection)->create($attributes);
        }

        return \TechraysLabs\Webhooker\Models\WebhookBatch::create($attributes);
    }

    public function findBatch(string $batchId): ?\TechraysLabs\Webhooker\Models\WebhookBatch
    {
        return $this->readQuery(\TechraysLabs\Webhooker\Models\WebhookBatch::class)
            ->where('batch_id', $batchId)
            ->first();
    }

    public function updateBatch(\TechraysLabs\Webhooker\Models\WebhookBatch $batch, array $attributes): bool
    {
        return $batch->update($attributes);
    }

    /**
     * @return LengthAwarePaginator<int, \TechraysLabs\Webhooker\Models\WebhookBatch>
     */
    public function paginateBatches(int $perPage = 15): LengthAwarePaginator
    {
        return $this->readQuery(\TechraysLabs\Webhooker\Models\WebhookBatch::class)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    // Health Snapshots

    public function createHealthSnapshot(array $attributes): \TechraysLabs\Webhooker\Models\WebhookHealthSnapshot
    {
        if ($this->connection) {
            return \TechraysLabs\Webhooker\Models\WebhookHealthSnapshot::on($this->connection)->create($attributes);
        }

        return \TechraysLabs\Webhooker\Models\WebhookHealthSnapshot::create($attributes);
    }

    /**
     * @return Collection<int, \TechraysLabs\Webhooker\Models\WebhookHealthSnapshot>
     */
    public function getHealthHistory(int $endpointId, int $days = 30): Collection
    {
        return $this->readQuery(\TechraysLabs\Webhooker\Models\WebhookHealthSnapshot::class)
            ->where('endpoint_id', $endpointId)
            ->where('recorded_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('recorded_at')
            ->get();
    }

    public function pruneHealthSnapshots(int $days): int
    {
        $cutoff = Carbon::now()->subDays($days);

        return $this->writeQuery(\TechraysLabs\Webhooker\Models\WebhookHealthSnapshot::class)
            ->where('recorded_at', '<', $cutoff)
            ->delete();
    }

    // Attempts

    public function createAttempt(array $attributes): WebhookAttempt
    {
        if ($this->connection) {
            return WebhookAttempt::on($this->connection)->create($attributes);
        }

        return WebhookAttempt::create($attributes);
    }

    /**
     * @return Collection<int, WebhookAttempt>
     */
    public function getAttemptsForEvent(int $eventId): Collection
    {
        return $this->readQuery(WebhookAttempt::class)
            ->where('event_id', $eventId)
            ->orderByDesc('attempted_at')
            ->get();
    }

    public function inboundEventExists(int $endpointId, string $eventName): bool
    {
        return $this->readQuery(WebhookEvent::class)
            ->where('endpoint_id', $endpointId)
            ->where('event_name', $eventName)
            ->exists();
    }

    // Tags

    /**
     * @return Collection<int, WebhookEndpoint>
     */
    public function getEndpointsByTag(string $tag): Collection
    {
        return $this->readQuery(WebhookEndpoint::class)
            ->where('is_active', true)
            ->whereHas('tags', function ($query) use ($tag) {
                $query->where('tag', $tag);
            })
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    public function getAllTags(): Collection
    {
        return $this->readQuery(WebhookEndpointTag::class)->distinct()->pluck('tag');
    }

    // Connection helpers

    /**
     * Get a query builder for read operations (uses read replica if configured).
     *
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return Builder<T>
     */
    private function readQuery(string $model): Builder
    {
        $conn = $this->readConnection ?? $this->connection;

        if ($conn) {
            return $model::on($conn)->newQuery();
        }

        return $model::query();
    }

    /**
     * Get a query builder for write operations (uses primary connection).
     *
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return Builder<T>
     */
    private function writeQuery(string $model): Builder
    {
        if ($this->connection) {
            return $model::on($this->connection)->newQuery();
        }

        return $model::query();
    }
}
