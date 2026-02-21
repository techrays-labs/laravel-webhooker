<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Support;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Structured logging helper for webhook activity.
 *
 * Uses a dedicated log channel when configured, otherwise falls back
 * to Laravel's default channel.
 */
class WebhookLogger
{
    private function channel(): LoggerInterface
    {
        $channel = config('webhooks.logging.channel');

        if ($channel !== null) {
            return Log::channel($channel);
        }

        return Log::getLogger();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->channel()->info("[Webhooker] {$message}", $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->channel()->warning("[Webhooker] {$message}", $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->channel()->error("[Webhooker] {$message}", $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->channel()->debug("[Webhooker] {$message}", $context);
    }
}
