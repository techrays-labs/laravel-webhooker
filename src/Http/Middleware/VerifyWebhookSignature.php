<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;

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
        $routeToken = (string) $request->route('endpoint');
        $endpoint = $this->repository->findEndpointByRouteToken($routeToken);

        if ($endpoint === null || ! $endpoint->is_active || ! $endpoint->isInbound()) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');

        /** @var string $signature */
        $signature = $request->header($signatureHeader) ?? '';

        if (empty($signature)) {
            return response()->json(['error' => 'Missing signature.'], 401);
        }

        $payload = $request->getContent();

        if (! $this->signer->verify($payload, $endpoint->secret, $signature)) {
            // Check previous secret during grace period
            if ($this->isWithinGracePeriod($endpoint)
                && $this->signer->verify($payload, $endpoint->previous_secret, $signature)) {
                $request->attributes->set('webhook_endpoint', $endpoint);

                return $next($request);
            }

            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $request->attributes->set('webhook_endpoint', $endpoint);

        return $next($request);
    }

    private function isWithinGracePeriod(WebhookEndpoint $endpoint): bool
    {
        if ($endpoint->previous_secret === null || $endpoint->secret_rotated_at === null) {
            return false;
        }

        $graceHours = (int) config('webhooks.secret_rotation.grace_period_hours', 24);

        return $endpoint->secret_rotated_at->addHours($graceHours)->isFuture();
    }
}
