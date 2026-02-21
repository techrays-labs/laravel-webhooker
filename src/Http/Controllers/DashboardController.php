<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Enums\CircuitState;
use TechraysLabs\Webhooker\Jobs\DispatchWebhookJob;
use TechraysLabs\Webhooker\Models\WebhookEvent;

/**
 * Dashboard controller for viewing webhook endpoints, events, and attempts.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly WebhookRepository $repository,
    ) {}

    /**
     * Display the list of webhook events with stats overview.
     */
    public function events(Request $request): View
    {
        $filters = $request->only(['status', 'endpoint_id', 'event_name']);
        $events = $this->repository->paginateEvents($filters, 20);
        $endpoints = $this->repository->getActiveEndpoints();

        $metrics = app(WebhookMetrics::class);
        $stats = $metrics->summary('outbound', Carbon::now()->subHours(24));

        $circuitBreaker = app(CircuitBreaker::class);
        $allEndpoints = $this->repository->getActiveEndpoints('outbound');
        $healthCounts = ['healthy' => 0, 'degraded' => 0, 'failing' => 0, 'unknown' => 0];
        $openCircuits = 0;

        foreach ($allEndpoints as $ep) {
            $health = $metrics->endpointHealth($ep->id);
            $healthCounts[$health->status] = ($healthCounts[$health->status] ?? 0) + 1;

            if ($circuitBreaker->getState($ep) === CircuitState::OPEN) {
                $openCircuits++;
            }
        }

        $successRate = $stats->totalEvents > 0
            ? round(($stats->successfulCount / $stats->totalEvents) * 100, 1)
            : 100.0;

        /** @var view-string $view */
        $view = 'webhooker::events.index';

        return view($view, compact('events', 'filters', 'endpoints', 'stats', 'healthCounts', 'openCircuits', 'successRate'));
    }

    /**
     * Display a single webhook event with its attempts timeline.
     */
    public function showEvent(int $event): View
    {
        $webhookEvent = $this->repository->findEvent($event);

        if ($webhookEvent === null) {
            abort(404);
        }

        $attempts = $this->repository->getAttemptsForEvent($webhookEvent->id);

        /** @var view-string $view */
        $view = 'webhooker::events.show';

        return view($view, compact('webhookEvent', 'attempts'));
    }

    /**
     * Display the list of webhook endpoints.
     */
    public function endpoints(): View
    {
        $endpoints = $this->repository->paginateEndpoints(20);

        /** @var view-string $view */
        $view = 'webhooker::endpoints.index';

        return view($view, compact('endpoints'));
    }

    /**
     * Display the endpoint detail page.
     */
    public function showEndpoint(int $endpoint): View
    {
        $endpointModel = $this->repository->findEndpoint($endpoint);

        if ($endpointModel === null) {
            abort(404);
        }

        $recentEvents = WebhookEvent::where('endpoint_id', $endpoint)
            ->orderByDesc('created_at')
            ->paginate(20);

        $circuitBreaker = app(CircuitBreaker::class);
        $circuitState = $circuitBreaker->getState($endpointModel);
        $health = app(WebhookMetrics::class)->endpointHealth($endpointModel->id);

        // Build 7-day sparkline data
        $sparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = Carbon::now()->subDays($i)->startOfDay();
            $dayEnd = Carbon::now()->subDays($i)->endOfDay();

            $dayTotal = WebhookEvent::where('endpoint_id', $endpoint)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $daySuccess = WebhookEvent::where('endpoint_id', $endpoint)
                ->where('status', WebhookEvent::STATUS_DELIVERED)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $sparkline[] = [
                'date' => $dayStart->format('M d'),
                'total' => $dayTotal,
                'success' => $daySuccess,
                'rate' => $dayTotal > 0 ? round(($daySuccess / $dayTotal) * 100) : 0,
            ];
        }

        /** @var view-string $view */
        $view = 'webhooker::endpoints.show';

        return view($view, compact('endpointModel', 'recentEvents', 'circuitState', 'health', 'sparkline'));
    }

    /**
     * Handle bulk actions on events.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $action = $request->input('action');
        $eventIds = $request->input('event_ids', []);

        if (empty($eventIds) || ! is_array($eventIds)) {
            return redirect()->route('webhooker.events.index')
                ->with('error', 'No events selected.');
        }

        $maxBatch = (int) config('webhooks.dashboard.max_bulk_size', 100);
        $eventIds = array_slice($eventIds, 0, $maxBatch);
        $count = count($eventIds);

        match ($action) {
            'replay' => $this->bulkReplay($eventIds),
            'delete' => $this->bulkDelete($eventIds),
            default => null,
        };

        $label = $action === 'replay' ? 'replayed' : 'deleted';

        return redirect()->route('webhooker.events.index')
            ->with('success', "{$count} event(s) {$label} successfully.");
    }

    /**
     * @param  array<int, string|int>  $eventIds
     */
    private function bulkReplay(array $eventIds): void
    {
        foreach ($eventIds as $eventId) {
            $event = $this->repository->findEvent((int) $eventId);

            if ($event === null) {
                continue;
            }

            $this->repository->updateEvent($event, [
                'status' => WebhookEvent::STATUS_PENDING,
                'next_retry_at' => null,
            ]);

            DispatchWebhookJob::dispatch($event->id);
        }
    }

    /**
     * @param  array<int, string|int>  $eventIds
     */
    private function bulkDelete(array $eventIds): void
    {
        WebhookEvent::whereIn('id', $eventIds)->delete();
    }
}
