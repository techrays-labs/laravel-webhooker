<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Contracts;

interface WebhookPlugin
{
    public function getName(): string;

    public function onWebhookSending(array $payload, int $endpointId): array;

    public function onWebhookDelivered(int $eventId, array $response): void;

    public function onWebhookFailed(int $eventId, \Throwable $exception): void;

    public function transformPayload(array $payload, int $endpointId): array;

    public function filterEvent(string $eventName, int $endpointId): bool;
}
