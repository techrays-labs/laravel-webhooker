<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use TechraysLabs\Webhooker\Models\WebhookBatch;

/**
 * Fired when all events in a batch have been processed successfully.
 */
class WebhookBatchCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookBatch $batch,
    ) {}
}
