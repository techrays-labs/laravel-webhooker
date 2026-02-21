<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Exceptions;

use Illuminate\Support\MessageBag;

class InvalidWebhookPayloadException extends \RuntimeException
{
    public function __construct(
        public readonly string $eventName,
        public readonly MessageBag $errors,
    ) {
        parent::__construct("Invalid payload for webhook event [{$eventName}]: {$errors->first()}");
    }
}
