<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Models\WebhookEndpoint;

/**
 * Artisan command to simulate inbound webhook deliveries for local development.
 */
class SimulateCommand extends Command
{
    protected $signature = 'webhook:simulate {type=custom : Template type (custom, generic, stripe, github)}
        {--endpoint= : The route_token of the inbound endpoint}
        {--payload= : Path to a JSON file with the payload}
        {--json= : Inline JSON payload}
        {--event= : Event type name for the template}';

    protected $description = 'Simulate an inbound webhook delivery for local development';

    public function handle(SignatureGenerator $signer): int
    {
        $type = $this->argument('type');
        $endpointToken = $this->option('endpoint');

        if ($endpointToken === null) {
            $this->error('The --endpoint option is required.');

            return self::FAILURE;
        }

        $endpoint = WebhookEndpoint::where('route_token', $endpointToken)->first();

        if ($endpoint === null) {
            $this->error("Endpoint with token '{$endpointToken}' not found.");

            return self::FAILURE;
        }

        if (! $endpoint->isInbound()) {
            $this->error('The specified endpoint is not an inbound endpoint.');

            return self::FAILURE;
        }

        $payload = $this->resolvePayload($type);

        if ($payload === null) {
            $this->error('Could not resolve payload. Use --json or --payload options.');

            return self::FAILURE;
        }

        $payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $signature = $signer->generate($payloadJson, $endpoint->secret);
        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');

        $url = url("/api/webhooks/inbound/{$endpoint->route_token}");

        $this->info("Sending simulated webhook to: {$url}");
        $this->line("Payload: {$payloadJson}");

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                $signatureHeader => $signature,
            ])->withBody($payloadJson, 'application/json')->post($url);

            $this->info("Response Status: {$response->status()}");
            $this->line("Response Body: {$response->body()}");

            return $response->successful() ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("Request failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(string $type): ?array
    {
        $jsonOption = $this->option('json');
        if ($jsonOption !== null) {
            $decoded = json_decode($jsonOption, true);

            return is_array($decoded) ? $decoded : null;
        }

        $payloadFile = $this->option('payload');
        if ($payloadFile !== null && file_exists($payloadFile)) {
            $decoded = json_decode(file_get_contents($payloadFile), true);

            return is_array($decoded) ? $decoded : null;
        }

        $eventName = $this->option('event') ?? 'test.event';

        return match ($type) {
            'generic' => $this->genericTemplate($eventName),
            'stripe' => $this->stripeTemplate($eventName),
            'github' => $this->githubTemplate($eventName),
            'custom' => $this->genericTemplate($eventName),
            default => $this->genericTemplate($eventName),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function genericTemplate(string $eventName): array
    {
        return [
            'event' => $eventName,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'id' => 'test_'.uniqid(),
                'message' => 'This is a simulated webhook event.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stripeTemplate(string $eventName): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => $eventName,
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'pi_'.uniqid(),
                    'amount' => 2000,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function githubTemplate(string $eventName): array
    {
        return [
            'action' => $eventName,
            'repository' => [
                'id' => 123456,
                'name' => 'example-repo',
                'full_name' => 'user/example-repo',
            ],
            'sender' => [
                'login' => 'test-user',
                'id' => 789,
            ],
        ];
    }
}
