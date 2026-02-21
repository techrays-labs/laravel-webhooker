<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Webhooker;

/**
 * Artisan command to enable a webhook endpoint.
 */
class EndpointEnableCommand extends Command
{
    protected $signature = 'webhook:endpoint:enable {id : The ID of the endpoint to enable}';

    protected $description = 'Enable a webhook endpoint';

    public function handle(Webhooker $webhooker): int
    {
        $endpointId = (int) $this->argument('id');

        $webhooker->enable($endpointId);

        $this->info("Endpoint #{$endpointId} has been enabled.");

        return self::SUCCESS;
    }
}
