<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use Illuminate\Support\Facades\Cache;
use TechraysLabs\Webhooker\Contracts\WebhookLock;

/**
 * Cache-based implementation of the WebhookLock contract.
 *
 * Uses Laravel's Cache lock mechanism, which supports Redis, database,
 * and other lock-capable cache drivers.
 */
class CacheLockProvider implements WebhookLock
{
    public function acquireEventLock(int $eventId, int $ttl = 300): bool
    {
        return Cache::lock("webhooker:event_lock:{$eventId}", $ttl)->get();
    }

    public function releaseEventLock(int $eventId): void
    {
        Cache::lock("webhooker:event_lock:{$eventId}")->forceRelease();
    }

    public function acquireNamedLock(string $name, int $ttl = 300): bool
    {
        return Cache::lock("webhooker:lock:{$name}", $ttl)->get();
    }

    public function releaseNamedLock(string $name): void
    {
        Cache::lock("webhooker:lock:{$name}")->forceRelease();
    }
}
