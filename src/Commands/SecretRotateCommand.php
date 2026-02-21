<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Events\EndpointSecretRotated;
use Illuminate\Support\Str;

/**
 * Artisan command to rotate an endpoint's secret.
 */
class SecretRotateCommand extends Command
{
    protected $signature = 'webhook:secret:rotate {endpoint_id : The ID of the endpoint}';

    protected $description = 'Rotate the secret for a webhook endpoint';

    public function handle(WebhookRepository $repository): int
    {
        $endpointId = (int) $this->argument('endpoint_id');
        $endpoint = $repository->findEndpoint($endpointId);

        if ($endpoint === null) {
            $this->error("Endpoint #{$endpointId} not found.");

            return self::FAILURE;
        }

        $newSecret = Str::random(64);

        $endpoint->update([
            'previous_secret' => $endpoint->secret,
            'secret_rotated_at' => now(),
            'secret' => $newSecret,
        ]);

        EndpointSecretRotated::dispatch($endpoint->fresh());

        $this->info("Secret rotated for endpoint '{$endpoint->name}'.");
        $this->line("New secret: {$newSecret}");
        $this->line('Previous secret will be accepted during the grace period.');

        return self::SUCCESS;
    }
}
