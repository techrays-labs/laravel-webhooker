<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use TechraysLabs\Webhooker\Models\WebhookAttempt;
use TechraysLabs\Webhooker\Models\WebhookBatch;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Models\WebhookHealthSnapshot;

/**
 * Repository contract for all webhook data persistence operations.
 *
 * All database interaction must go through this interface to ensure
 * storage driver abstraction and multi-database support.
 */
interface WebhookRepository
{
    // Endpoints

    /**
     * Create a new webhook endpoint.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createEndpoint(array $attributes): WebhookEndpoint;

    /**
     * Find an endpoint by its ID.
     */
    public function findEndpoint(int $id): ?WebhookEndpoint;

    /**
     * Find an endpoint by its route token.
     */
    public function findEndpointByRouteToken(string $routeToken): ?WebhookEndpoint;

    /**
     * Get all active endpoints, optionally filtered by direction.
     *
     * @return Collection<int, WebhookEndpoint>
     */
    public function getActiveEndpoints(?string $direction = null): Collection;

    /**
     * Get all endpoints with pagination.
     *
     * @return LengthAwarePaginator<int, WebhookEndpoint>
     */
    public function paginateEndpoints(int $perPage = 15): LengthAwarePaginator;

    /**
     * Update an endpoint's attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateEndpoint(WebhookEndpoint $endpoint, array $attributes): bool;

    /**
     * Clean up expired previous secrets past the grace period.
     *
     * @return int Number of endpoints cleaned
     */
    public function cleanExpiredSecrets(int $gracePeriodHours): int;

    // Events

    /**
     * Create a new webhook event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createEvent(array $attributes): WebhookEvent;

    /**
     * Find a webhook event by its ID.
     */
    public function findEvent(int $id): ?WebhookEvent;

    /**
     * Find a webhook event by its idempotency key.
     */
    public function findEventByIdempotencyKey(string $key): ?WebhookEvent;

    /**
     * Get the most recent failed event.
     */
    public function getLastFailedEvent(): ?WebhookEvent;

    /**
     * Get events ready for retry (status=pending or failed, next_retry_at <= now).
     *
     * @return Collection<int, WebhookEvent>
     */
    public function getRetryableEvents(): Collection;

    /**
     * Update a webhook event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateEvent(WebhookEvent $event, array $attributes): bool;

    /**
     * Paginate events with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WebhookEvent>
     */
    public function paginateEvents(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Delete events by their IDs.
     *
     * @param  array<int, int>  $eventIds
     * @return int Number of deleted records
     */
    public function deleteEvents(array $eventIds): int;

    /**
     * Get recent events for a specific endpoint with pagination.
     *
     * @return LengthAwarePaginator<int, WebhookEvent>
     */
    public function getRecentEventsForEndpoint(int $endpointId, int $perPage = 20): LengthAwarePaginator;

    /**
     * Get event counts per day for a specific endpoint within a date range.
     *
     * @return Collection<int, array{date: string, total: int, success: int}>
     */
    public function getEventCountsForEndpointByDay(int $endpointId, Carbon $start, Carbon $end): Collection;

    /**
     * Get events matching filters (for bulk operations).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, WebhookEvent>
     */
    public function getFilteredEvents(array $filters = [], int $limit = 100): Collection;

    /**
     * Prune events older than the given number of days.
     *
     * @return int Number of deleted records
     */
    public function pruneEvents(int $days): int;

    // Dead-Letter Queue

    /**
     * Get paginated dead-letter events.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WebhookEvent>
     */
    public function paginateDeadLetterEvents(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Move an event to the dead-letter queue.
     */
    public function moveToDeadLetter(WebhookEvent $event, string $reason): bool;

    /**
     * Restore a dead-letter event for retry.
     */
    public function restoreFromDeadLetter(WebhookEvent $event): bool;

    /**
     * Prune old dead-letter events.
     *
     * @return int Number of deleted records
     */
    public function pruneDeadLetterEvents(int $days): int;

    /**
     * Count dead-letter events.
     */
    public function countDeadLetterEvents(): int;

    // Batches

    /**
     * Create a new webhook batch.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createBatch(array $attributes): WebhookBatch;

    /**
     * Find a batch by its UUID.
     */
    public function findBatch(string $batchId): ?WebhookBatch;

    /**
     * Update a batch's attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateBatch(WebhookBatch $batch, array $attributes): bool;

    /**
     * Get paginated batches.
     *
     * @return LengthAwarePaginator<int, WebhookBatch>
     */
    public function paginateBatches(int $perPage = 15): LengthAwarePaginator;

    // Health Snapshots

    /**
     * Create a health snapshot record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createHealthSnapshot(array $attributes): WebhookHealthSnapshot;

    /**
     * Get health history for an endpoint.
     *
     * @return Collection<int, WebhookHealthSnapshot>
     */
    public function getHealthHistory(int $endpointId, int $days = 30): Collection;

    /**
     * Prune old health snapshots.
     *
     * @return int Number of deleted records
     */
    public function pruneHealthSnapshots(int $days): int;

    // Attempts

    /**
     * Create a webhook delivery attempt record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createAttempt(array $attributes): WebhookAttempt;

    /**
     * Get attempts for a specific event.
     *
     * @return Collection<int, WebhookAttempt>
     */
    public function getAttemptsForEvent(int $eventId): Collection;

    /**
     * Check if an inbound event with the given name and endpoint already exists.
     */
    public function inboundEventExists(int $endpointId, string $eventName): bool;

    // Tags

    /**
     * Get active endpoints that have a specific tag.
     *
     * @return Collection<int, WebhookEndpoint>
     */
    public function getEndpointsByTag(string $tag): Collection;

    /**
     * Get all distinct tags.
     *
     * @return Collection<int, string>
     */
    public function getAllTags(): Collection;
}
