<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use TechRaysLabs\Webhooker\Models\WebhookAttempt;
use TechRaysLabs\Webhooker\Models\WebhookEndpoint;
use TechRaysLabs\Webhooker\Models\WebhookEvent;

/**
 * Repository contract for all webhook data persistence operations.
 *
 * All database interaction must go through this interface to ensure
 * Phase 2 scalability (storage driver abstraction, multi-database, etc).
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
     * Get all active endpoints, optionally filtered by direction.
     */
    public function getActiveEndpoints(?string $direction = null): Collection;

    /**
     * Get all endpoints with pagination.
     */
    public function paginateEndpoints(int $perPage = 15): LengthAwarePaginator;

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
     * Get events ready for retry (status=pending or failed, next_retry_at <= now).
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
     */
    public function paginateEvents(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Prune events older than the given number of days.
     *
     * @return int Number of deleted records
     */
    public function pruneEvents(int $days): int;

    // Attempts

    /**
     * Create a webhook delivery attempt record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createAttempt(array $attributes): WebhookAttempt;

    /**
     * Get attempts for a specific event.
     */
    public function getAttemptsForEvent(int $eventId): Collection;

    /**
     * Check if an inbound event with the given name and endpoint already exists.
     */
    public function inboundEventExists(int $endpointId, string $eventName): bool;
}
