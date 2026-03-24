<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use TechraysLabs\Webhooker\Models\WebhookApiToken;

class WebhookApiTokenController extends Controller
{
    public function index(): JsonResponse
    {
        $tokens = WebhookApiToken::query()
            ->select(['id', 'name', 'abilities', 'expires_at', 'last_used_at', 'is_active', 'created_at'])
            ->paginate(15);

        return response()->json($tokens);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $token = WebhookApiToken::createToken(
            $validated['name'],
            $validated['abilities'] ?? null,
            $validated['expires_at'] ?? null
        );

        return response()->json($token, 201);
    }

    public function destroy(WebhookApiToken $token): JsonResponse
    {
        $token->delete();

        return response()->json(['message' => 'Token revoked successfully']);
    }

    public function update(Request $request, WebhookApiToken $token): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'boolean',
            'abilities' => 'array',
            'abilities.*' => 'string',
        ]);

        $token->update($validated);

        return response()->json($token);
    }
}
