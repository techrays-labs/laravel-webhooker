<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Contracts;

/**
 * Contract for distributed locking in webhook processing.
 *
 * Prevents duplicate processing of events across multiple workers
 * and ensures scheduled tasks run on only one node.
 */
interface WebhookLock
{
    /**
     * Acquire a lock for processing an event.
     *
     * @return bool True if lock acquired, false if already locked
     */
    public function acquireEventLock(int $eventId, int $ttl = 300): bool;

    /**
     * Release a lock for an event.
     */
    public function releaseEventLock(int $eventId): void;

    /**
     * Acquire a named lock (for scheduled tasks, batch operations, etc.).
     *
     * @return bool True if lock acquired, false if already locked
     */
    public function acquireNamedLock(string $name, int $ttl = 300): bool;

    /**
     * Release a named lock.
     */
    public function releaseNamedLock(string $name): void;
}
