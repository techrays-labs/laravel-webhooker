<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;
use TechraysLabs\Webhooker\Support\WebhookLogger;

/**
 * Middleware to check inbound webhook requests against IP allowlists.
 */
class CheckIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('webhooks.inbound.ip_allowlist.enabled', false)) {
            return $next($request);
        }

        $endpointToken = $request->route('endpoint');
        $endpoint = WebhookEndpoint::where('route_token', $endpointToken)->first();

        if ($endpoint === null) {
            return $next($request);
        }

        $clientIp = $this->resolveClientIp($request);
        $globalAllowlist = config('webhooks.inbound.ip_allowlist.global', []);
        $endpointAllowlist = $endpoint->allowed_ips ?? [];

        $combinedAllowlist = array_merge($globalAllowlist, $endpointAllowlist);

        if (empty($combinedAllowlist)) {
            return $next($request);
        }

        if (! $this->isIpAllowed($clientIp, $combinedAllowlist)) {
            $logger = new WebhookLogger;
            $logger->warning('Inbound webhook blocked by IP allowlist', [
                'endpoint' => $endpoint->route_token,
                'ip' => $clientIp,
            ]);

            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }

    private function resolveClientIp(Request $request): string
    {
        if (config('webhooks.inbound.ip_allowlist.trust_proxy', false)) {
            $forwarded = $request->header('X-Forwarded-For');
            if ($forwarded !== null) {
                $ips = array_map('trim', explode(',', $forwarded));

                return $ips[0];
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    /**
     * @param  array<int, string>  $allowlist
     */
    private function isIpAllowed(string $ip, array $allowlist): bool
    {
        foreach ($allowlist as $allowed) {
            if (str_contains($allowed, '/')) {
                if ($this->ipInCidr($ip, $allowed)) {
                    return true;
                }
            } elseif ($ip === $allowed) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);

        if ($ip === false || $subnet === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);
        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }
}
