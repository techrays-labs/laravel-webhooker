<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Services;

use Illuminate\Support\Facades\Validator;
use TechraysLabs\Webhooker\Contracts\PayloadValidator;
use TechraysLabs\Webhooker\Exceptions\InvalidWebhookPayloadException;
use TechraysLabs\Webhooker\Support\WebhookLogger;

/**
 * Config-based payload validator using Laravel's Validator.
 */
class ConfigPayloadValidator implements PayloadValidator
{
    public function validate(string $eventName, array $payload): void
    {
        if (! config('webhooks.payload_validation.enabled', false)) {
            return;
        }

        $schemas = config('webhooks.payload_validation.schemas', []);

        if (! isset($schemas[$eventName])) {
            return;
        }

        $validator = Validator::make($payload, $schemas[$eventName]);

        if ($validator->fails()) {
            $logger = new WebhookLogger;
            $logger->warning('Webhook payload validation failed', [
                'event_name' => $eventName,
                'errors' => $validator->errors()->toArray(),
            ]);

            throw new InvalidWebhookPayloadException($eventName, $validator->errors());
        }
    }
}
