<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Facades;

use Illuminate\Support\Facades\Facade;
use TechraysLabs\Webhooker\Testing\WebhookFake;
use TechraysLabs\Webhooker\Webhooker;

/**
 * @method static \TechraysLabs\Webhooker\Models\WebhookEvent dispatch(int $endpointId, string $eventName, array<string, mixed> $payload, array<string, mixed> $options = [])
 * @method static array<int, \TechraysLabs\Webhooker\Models\WebhookEvent> broadcast(string $eventName, array<string, mixed> $payload)
 * @method static array<int, \TechraysLabs\Webhooker\Models\WebhookEvent> dispatchToTag(string $tag, string $eventName, array<string, mixed> $payload)
 * @method static \TechraysLabs\Webhooker\Models\WebhookEndpoint registerEndpoint(array<string, mixed> $attributes)
 * @method static void disable(int $endpointId, ?string $reason = null)
 * @method static void enable(int $endpointId)
 * @method static bool isEnabled(int $endpointId)
 * @method static array<string, mixed>|null inspect(int $eventId)
 * @method static \TechraysLabs\Webhooker\Models\WebhookEvent|null lastFailed()
 * @method static \TechraysLabs\Webhooker\Models\WebhookEvent|null retryLast()
 * @method static \TechraysLabs\Webhooker\DTOs\MetricsSummary stats(string $direction = 'outbound')
 * @method static string rotateSecret(int $endpointId)
 * @method static \TechraysLabs\Webhooker\Models\WebhookBatch dispatchBatch(array $endpointIds, string $eventName, array $payload, array $options = [])
 * @method static \TechraysLabs\Webhooker\Models\WebhookBatch broadcastBatch(string $eventName, array $payload, array $options = [])
 * @method static \TechraysLabs\Webhooker\Models\WebhookBatch|null batchStatus(string $batchId)
 *
 * @see \TechraysLabs\Webhooker\Webhooker
 */
class Webhook extends Facade
{
    /**
     * Replace the Webhooker service with a fake.
     */
    public static function fake(): WebhookFake
    {
        $fake = new WebhookFake;

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return Webhooker::class;
    }
}
