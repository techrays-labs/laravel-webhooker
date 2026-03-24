<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Models\WebhookApiToken;

class WebhookEndpointController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $endpoints = WebhookEndpoint::query()
            ->when($request->has('direction'), fn ($q) => $q->where('direction', $request->direction))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->paginate($request->integer('per_page', 15));

        return response()->json($endpoints);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'direction' => 'required|in:inbound,outbound',
            'secret' => 'nullable|string|max:512',
            'is_active' => 'boolean',
            'timeout_seconds' => 'integer|min:1|max:300',
            'max_retries' => 'integer|min:0|max:100',
            'retry_strategy' => 'nullable|string|max:255',
            'rate_limit_per_minute' => 'nullable|integer|min:1',
            'allowed_ips' => 'array',
            'allowed_ips.*' => 'ip',
            'event_filters' => 'array',
            'event_filters.*' => 'string',
            'transform_config' => 'array',
            'transformer_class' => 'nullable|string',
        ]);

        $endpoint = WebhookEndpoint::create($validated);

        return response()->json($endpoint, 201);
    }

    public function show(WebhookEndpoint $endpoint): JsonResponse
    {
        return response()->json($endpoint->load(['tags', 'events']));
    }

    public function update(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'url' => 'url|max:2048',
            'direction' => 'in:inbound,outbound',
            'secret' => 'nullable|string|max:512',
            'is_active' => 'boolean',
            'timeout_seconds' => 'integer|min:1|max:300',
            'max_retries' => 'integer|min:0|max:100',
            'retry_strategy' => 'nullable|string|max:255',
            'rate_limit_per_minute' => 'nullable|integer|min:1',
            'allowed_ips' => 'array',
            'allowed_ips.*' => 'ip',
            'event_filters' => 'array',
            'event_filters.*' => 'string',
            'transform_config' => 'array',
            'transformer_class' => 'nullable|string',
        ]);

        $endpoint->update($validated);

        return response()->json($endpoint);
    }

    public function destroy(WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->delete();

        return response()->json(['message' => 'Endpoint deleted successfully']);
    }

    public function enable(WebhookEndpoint $endpoint): JsonResponse
    {
        $endpoint->update([
            'is_active' => true,
            'disabled_at' => null,
            'disabled_reason' => null,
        ]);

        return response()->json($endpoint);
    }

    public function disable(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $endpoint->update([
            'is_active' => false,
            'disabled_at' => now(),
            'disabled_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json($endpoint);
    }
}
