<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use TechraysLabs\Webhooker\Models\WebhookEventSchema;

class WebhookEventSchemaController extends Controller
{
    public function index(): JsonResponse
    {
        $schemas = WebhookEventSchema::query()
            ->when(request('is_active'), fn ($q) => $q->where('is_active', request()->boolean('is_active')))
            ->paginate(15);

        return response()->json($schemas);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_name' => 'required|string|max:255|unique:webhook_event_schemas',
            'schema' => 'required|array',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $schema = WebhookEventSchema::create($validated);

        return response()->json($schema, 201);
    }

    public function show(WebhookEventSchema $schema): JsonResponse
    {
        return response()->json($schema);
    }

    public function update(Request $request, WebhookEventSchema $schema): JsonResponse
    {
        $validated = $request->validate([
            'event_name' => 'string|max:255|unique:webhook_event_schemas,event_name,'.$schema->id,
            'schema' => 'array',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $schema->update($validated);

        return response()->json($schema);
    }

    public function destroy(WebhookEventSchema $schema): JsonResponse
    {
        $schema->delete();

        return response()->json(['message' => 'Schema deleted successfully']);
    }

    public function validate(Request $request, string $eventName): JsonResponse
    {
        $schema = WebhookEventSchema::findByEventName($eventName);

        if (!$schema) {
            return response()->json(['error' => 'Schema not found'], 404);
        }

        $validated = $request->validate([
            'payload' => 'required|array',
        ]);

        $isValid = $schema->validatePayload($validated['payload']);

        return response()->json([
            'valid' => $isValid,
            'event_name' => $eventName,
        ]);
    }
}
