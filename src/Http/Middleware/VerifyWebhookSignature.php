<?php

declare(strict_types=1);

namespace TechRaysLabs\Webhooker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use TechRaysLabs\Webhooker\Contracts\SignatureGenerator;
use TechRaysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Middleware to verify inbound webhook signatures using HMAC.
 */
class VerifyWebhookSignature
{
    public function __construct(
        private readonly SignatureGenerator $signer,
        private readonly WebhookRepository $repository,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $endpointId = (int) $request->route('endpoint');
        $endpoint = $this->repository->findEndpoint($endpointId);

        if ($endpoint === null || ! $endpoint->is_active || ! $endpoint->isInbound()) {
            return response()->json(['error' => 'Invalid endpoint.'], 404);
        }

        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');
        $signature = $request->header($signatureHeader, '');

        if (empty($signature)) {
            return response()->json(['error' => 'Missing signature.'], 401);
        }

        $payload = $request->getContent();

        if (! $this->signer->verify($payload, $endpoint->secret, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $request->attributes->set('webhook_endpoint', $endpoint);

        return $next($request);
    }
}
