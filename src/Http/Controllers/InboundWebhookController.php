<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use TechRaysLabs\Webhooker\Contracts\WebhookRepository;
use TechRaysLabs\Webhooker\Jobs\ProcessInboundWebhookJob;
use TechRaysLabs\Webhooker\Models\WebhookEvent;

/**
 * Controller for receiving inbound webhook payloads.
 */
class InboundWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookRepository $repository,
    ) {}

    public function __invoke(Request $request, int $endpoint): JsonResponse
    {
        $webhookEndpoint = $request->attributes->get('webhook_endpoint');
        $payload = $request->json()->all();
        $eventName = $request->header('X-Webhook-Event', $request->input('event', 'unknown'));

        // Deduplicate via event ID header
        $eventId = $request->header('X-Webhook-Event-ID');
        if ($eventId !== null && $this->repository->inboundEventExists($webhookEndpoint->id, $eventId)) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        $event = $this->repository->createEvent([
            'endpoint_id' => $webhookEndpoint->id,
            'event_name' => $eventId ?: $eventName,
            'payload' => $payload,
            'status' => WebhookEvent::STATUS_PENDING,
            'attempts_count' => 0,
        ]);

        ProcessInboundWebhookJob::dispatch($event->id);

        return response()->json(['status' => 'accepted', 'event_id' => $event->id], 202);
    }
}
