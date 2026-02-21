<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Contracts;

/**
 * Contract for validating outbound webhook payloads before dispatch.
 */
interface PayloadValidator
{
    /**
     * Validate the payload for a given event name.
     *
     * @param  string  $eventName  The event type.
     * @param  array<string, mixed>  $payload  The payload to validate.
     *
     * @throws \TechraysLabs\Webhooker\Exceptions\InvalidWebhookPayloadException
     */
    public function validate(string $eventName, array $payload): void;
}
