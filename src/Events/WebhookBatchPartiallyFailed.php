<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookBatch;

/**
 * Fired when a batch completes with some events having failed.
 */
class WebhookBatchPartiallyFailed
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookBatch $batch,
    ) {}
}
