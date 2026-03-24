<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Models\WebhookAttempt;
use Carbon\Carbon;

class WebhookAnalyticsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $startDate = $request->date('start_date', Carbon::now()->subDays(30));
        $endDate = $request->date('end_date', Carbon::now());

        $stats = [
            'total_endpoints' => WebhookEndpoint::count(),
            'active_endpoints' => WebhookEndpoint::where('is_active', true)->count(),
            'total_events' => WebhookEvent::whereBetween('created_at', [$startDate, $endDate])->count(),
            'delivered' => WebhookEvent::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', WebhookEvent::STATUS_DELIVERED)->count(),
            'failed' => WebhookEvent::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', WebhookEvent::STATUS_FAILED)->count(),
            'pending' => WebhookEvent::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', WebhookEvent::STATUS_PENDING)->count(),
            'success_rate' => 0,
            'average_response_time' => $this->getAverageResponseTime($startDate, $endDate),
            'top_endpoints' => $this->getTopEndpoints($startDate, $endDate),
            'events_over_time' => $this->getEventsOverTime($startDate, $endDate),
        ];

        if ($stats['total_events'] > 0) {
            $stats['success_rate'] = round(($stats['delivered'] / $stats['total_events']) * 100, 2);
        }

        return response()->json($stats);
    }

    public function endpointStats(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $startDate = $request->date('start_date', Carbon::now()->subDays(30));
        $endDate = $request->date('end_date', Carbon::now());

        $events = WebhookEvent::where('endpoint_id', $endpoint->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalEvents = $events->count();
        $delivered = (clone $events)->where('status', WebhookEvent::STATUS_DELIVERED)->count();
        $failed = (clone $events)->where('status', WebhookEvent::STATUS_FAILED)->count();

        return response()->json([
            'endpoint_id' => $endpoint->id,
            'endpoint_name' => $endpoint->name,
            'total_events' => $totalEvents,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => (clone $events)->where('status', WebhookEvent::STATUS_PENDING)->count(),
            'success_rate' => $totalEvents > 0 ? round(($delivered / $totalEvents) * 100, 2) : 0,
            'average_response_time' => $this->getAverageResponseTime($startDate, $endDate, $endpoint->id),
            'events_over_time' => $this->getEventsOverTime($startDate, $endDate, $endpoint->id),
        ]);
    }

    protected function getAverageResponseTime(Carbon $startDate, Carbon $endDate, ?int $endpointId = null): float
    {
        $query = WebhookAttempt::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('response_time_ms');

        if ($endpointId) {
            $query->whereHas('event', fn ($q) => $q->where('endpoint_id', $endpointId));
        }

        return round($query->avg('response_time_ms') ?? 0, 2);
    }

    protected function getTopEndpoints(Carbon $startDate, Carbon $endDate): array
    {
        return WebhookEndpoint::select('webhook_endpoints.id', 'webhook_endpoints.name')
            ->join('webhook_events', 'webhook_endpoints.id', '=', 'webhook_events.endpoint_id')
            ->whereBetween('webhook_events.created_at', [$startDate, $endDate])
            ->groupBy('webhook_endpoints.id', 'webhook_endpoints.name')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN webhook_events.status = ? THEN 1 ELSE 0 END) as delivered', [WebhookEvent::STATUS_DELIVERED])
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }

    protected function getEventsOverTime(Carbon $startDate, Carbon $endDate, ?int $endpointId = null): array
    {
        $query = WebhookEvent::whereBetween('created_at', [$startDate, $endDate]);

        if ($endpointId) {
            $query->where('endpoint_id', $endpointId);
        }

        return $query->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(fn ($items) => $items->pluck('count', 'status')->toArray())
            ->toArray();
    }
}
