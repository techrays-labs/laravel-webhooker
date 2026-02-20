<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use TechRaysLabs\Webhooker\Contracts\WebhookRepository;
use TechRaysLabs\Webhooker\Models\WebhookAttempt;
use TechRaysLabs\Webhooker\Models\WebhookEndpoint;
use TechRaysLabs\Webhooker\Models\WebhookEvent;

/**
 * Eloquent-based implementation of the WebhookRepository contract.
 */
class EloquentWebhookRepository implements WebhookRepository
{
    // Endpoints

    public function createEndpoint(array $attributes): WebhookEndpoint
    {
        return WebhookEndpoint::create($attributes);
    }

    public function findEndpoint(int $id): ?WebhookEndpoint
    {
        return WebhookEndpoint::find($id);
    }

    public function getActiveEndpoints(?string $direction = null): Collection
    {
        $query = WebhookEndpoint::where('is_active', true);

        if ($direction !== null) {
            $query->where('direction', $direction);
        }

        return $query->get();
    }

    public function paginateEndpoints(int $perPage = 15): LengthAwarePaginator
    {
        return WebhookEndpoint::orderByDesc('created_at')->paginate($perPage);
    }

    // Events

    public function createEvent(array $attributes): WebhookEvent
    {
        return WebhookEvent::create($attributes);
    }

    public function findEvent(int $id): ?WebhookEvent
    {
        return WebhookEvent::with('endpoint')->find($id);
    }

    public function getRetryableEvents(): Collection
    {
        return WebhookEvent::where('status', WebhookEvent::STATUS_PENDING)
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

    public function paginateEvents(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WebhookEvent::with('endpoint')->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['endpoint_id'])) {
            $query->where('endpoint_id', $filters['endpoint_id']);
        }

        if (! empty($filters['event_name'])) {
            $query->where('event_name', 'like', '%' . $filters['event_name'] . '%');
        }

        return $query->paginate($perPage);
    }

    public function pruneEvents(int $days): int
    {
        $cutoff = Carbon::now()->subDays($days);

        return WebhookEvent::where('created_at', '<', $cutoff)->delete();
    }

    // Attempts

    public function createAttempt(array $attributes): WebhookAttempt
    {
        return WebhookAttempt::create($attributes);
    }

    public function getAttemptsForEvent(int $eventId): Collection
    {
        return WebhookAttempt::where('event_id', $eventId)
            ->orderByDesc('attempted_at')
            ->get();
    }

    public function inboundEventExists(int $endpointId, string $eventName): bool
    {
        return WebhookEvent::where('endpoint_id', $endpointId)
            ->where('event_name', $eventName)
            ->exists();
    }
}
