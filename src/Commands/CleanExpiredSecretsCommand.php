<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Commands;

use Illuminate\Console\Command;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;

/**
 * Artisan command to clean up expired previous secrets after the grace period.
 *
 * Should be scheduled to run periodically (e.g., hourly) to remove
 * previous secrets that are no longer within the rotation grace period.
 */
class CleanExpiredSecretsCommand extends Command
{
    protected $signature = 'webhook:secret:cleanup';

    protected $description = 'Remove expired previous secrets that are past the grace period';

    public function handle(WebhookRepository $repository): int
    {
        $graceHours = (int) config('webhooks.secret_rotation.grace_period_hours', 24);

        $count = $repository->cleanExpiredSecrets($graceHours);

        $this->info("Cleaned up {$count} expired previous secret(s).");

        return self::SUCCESS;
    }
}
