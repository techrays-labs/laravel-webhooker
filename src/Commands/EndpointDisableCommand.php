<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Webhooker;

/**
 * Artisan command to disable a webhook endpoint.
 */
class EndpointDisableCommand extends Command
{
    protected $signature = 'webhook:endpoint:disable {id : The ID of the endpoint to disable} {--reason= : Reason for disabling}';

    protected $description = 'Disable a webhook endpoint';

    public function handle(Webhooker $webhooker): int
    {
        $endpointId = (int) $this->argument('id');
        $reason = $this->option('reason');

        $webhooker->disable($endpointId, $reason);

        $this->info("Endpoint #{$endpointId} has been disabled.");

        return self::SUCCESS;
    }
}
