<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Facades\Webhook;

class WebhookEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = WebhookEvent::query()
            ->with('endpoint')
            ->when($request->has('endpoint_id'), fn ($q) => $q->where('endpoint_id', $request->endpoint_id))
            ->when($request->has('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->has('event_name'), fn ($q) => $q->where('event_name', 'like', "%{$request->event_name}%"))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($events);
    }

    public function show(WebhookEvent $event): JsonResponse
    {
        return response()->json($event->load(['endpoint', 'attempts', 'batch']));
    }

    public function replay(WebhookEvent $event): JsonResponse
    {
        if (!$event->isFailed() && !$event->isDeadLetter()) {
            return response()->json(['error' => 'Event is not in a replayable state'], 400);
        }

        Webhook::replay($event);

        return response()->json(['message' => 'Event queued for replay']);
    }

    public function retry(WebhookEvent $event): JsonResponse
    {
        if (!$event->isFailed()) {
            return response()->json(['error' => 'Event is not in a failed state'], 400);
        }

        $event->update([
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
            'next_retry_at' => null,
        ]);

        Webhook::dispatch($event);

        return response()->json(['message' => 'Event queued for retry']);
    }

    public function destroy(WebhookEvent $event): JsonResponse
    {
        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }
}
