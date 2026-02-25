<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Storage;

use Illuminate\Support\Manager;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Services\EloquentWebhookRepository;

/**
 * Manager for resolving webhook storage drivers.
 *
 * Follows Laravel's Manager pattern to support swappable storage backends.
 * The default driver is 'eloquent', which uses Eloquent ORM.
 */
class WebhookStorageManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('webhooks.storage.driver', 'eloquent');
    }

    /**
     * Create the Eloquent storage driver.
     */
    public function createEloquentDriver(): WebhookRepository
    {
        $config = $this->config->get('webhooks.storage.drivers.eloquent', []);

        return new EloquentWebhookRepository(
            connection: $config['connection'] ?? null,
            readConnection: $config['read_connection'] ?? null,
        );
    }
}
