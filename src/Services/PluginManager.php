<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use Illuminate\Support\Collection;
use TechraysLabs\Webhooker\Contracts\WebhookPlugin as WebhookPluginContract;
use TechraysLabs\Webhooker\Models\WebhookPlugin as WebhookPluginModel;

class PluginManager
{
    protected ?Collection $plugins = null;

    public function getPlugins(): Collection
    {
        if ($this->plugins === null) {
            $this->plugins = WebhookPluginModel::getEnabledPlugins()
                ->map(fn ($model) => $model->getInstance())
                ->filter(fn ($plugin) => $plugin instanceof WebhookPluginContract);
        }

        return $this->plugins;
    }

    public function transformPayload(array $payload, int $endpointId): array
    {
        foreach ($this->getPlugins() as $plugin) {
            $payload = $plugin->transformPayload($payload, $endpointId);
        }

        return $payload;
    }

    public function onWebhookSending(array $payload, int $endpointId): array
    {
        foreach ($this->getPlugins() as $plugin) {
            $payload = $plugin->onWebhookSending($payload, $endpointId);
        }

        return $payload;
    }

    public function onWebhookDelivered(int $eventId, array $response): void
    {
        foreach ($this->getPlugins() as $plugin) {
            $plugin->onWebhookDelivered($eventId, $response);
        }
    }

    public function onWebhookFailed(int $eventId, \Throwable $exception): void
    {
        foreach ($this->getPlugins() as $plugin) {
            $plugin->onWebhookFailed($eventId, $exception);
        }
    }

    public function filterEvent(string $eventName, int $endpointId): bool
    {
        foreach ($this->getPlugins() as $plugin) {
            if (!$plugin->filterEvent($eventName, $endpointId)) {
                return false;
            }
        }

        return true;
    }

    public function flush(): void
    {
        $this->plugins = null;
    }
}
