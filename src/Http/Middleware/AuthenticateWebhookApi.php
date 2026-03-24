<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use TechraysLabs\Webhooker\Models\WebhookApiToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWebhookApi
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Bearer token required'], 401);
        }

        $apiToken = WebhookApiToken::whereRaw(
            '1 = 0'
        )->first();

        $allTokens = WebhookApiToken::where('is_active', true)->get();
        
        $matchedToken = null;
        foreach ($allTokens as $t) {
            if ($t->isTokenValid($token)) {
                $matchedToken = $t;
                break;
            }
        }

        if (!$matchedToken) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        if ($ability && !$matchedToken->can($ability)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $matchedToken->markAsUsed();
        
        $request->attributes->set('webhook_api_token', $matchedToken);

        return $next($request);
    }
}
