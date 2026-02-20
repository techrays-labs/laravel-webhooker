<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Contracts;

use Illuminate\Support\Carbon;

/**
 * Contract for determining when a failed webhook should be retried.
 */
interface RetryStrategy
{
    /**
     * Calculate the next retry time for a given attempt number.
     *
     * @param  int  $attempt  The current attempt number (1-based).
     * @return Carbon The timestamp for the next retry.
     */
    public function nextRetry(int $attempt): Carbon;

    /**
     * Determine if the event should be retried given the current attempt count.
     *
     * @param  int  $attempt  The current attempt number (1-based).
     */
    public function shouldRetry(int $attempt): bool;
}
