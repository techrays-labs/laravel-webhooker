<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use TechRaysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Dashboard controller for viewing webhook endpoints, events, and attempts.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly WebhookRepository $repository,
    ) {}

    /**
     * Display the list of webhook events.
     */
    public function events(Request $request): View
    {
        $filters = $request->only(['status', 'endpoint_id', 'event_name']);
        $events = $this->repository->paginateEvents($filters, 20);
        $endpoints = $this->repository->getActiveEndpoints();

        return view('webhooker::events.index', compact('events', 'filters', 'endpoints'));
    }

    /**
     * Display a single webhook event with its attempts.
     */
    public function showEvent(int $event): View
    {
        $webhookEvent = $this->repository->findEvent($event);

        if ($webhookEvent === null) {
            abort(404);
        }

        $attempts = $this->repository->getAttemptsForEvent($webhookEvent->id);

        return view('webhooker::events.show', compact('webhookEvent', 'attempts'));
    }

    /**
     * Display the list of webhook endpoints.
     */
    public function endpoints(): View
    {
        $endpoints = $this->repository->paginateEndpoints(20);

        return view('webhooker::endpoints.index', compact('endpoints'));
    }
}
