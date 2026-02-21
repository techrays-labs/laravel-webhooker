<?php

declare(strict_types=1);

namespace TechraysLabs\Webhooker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use TechraysLabs\Webhooker\Contracts\CircuitBreaker;
use TechraysLabs\Webhooker\Contracts\RetryStrategy;
use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Events\WebhookFailed;
use TechraysLabs\Webhooker\Events\WebhookRetriesExhausted;
use TechraysLabs\Webhooker\Events\WebhookSending;
use TechraysLabs\Webhooker\Events\WebhookSent;
use TechraysLabs\Webhooker\Models\WebhookEvent;
use TechraysLabs\Webhooker\Strategies\ExponentialBackoffRetry;
use TechraysLabs\Webhooker\Support\WebhookLogger;

/**
 * Queue job for delivering an outbound webhook event.
 */
class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $eventId,
        public readonly bool $force = false,
    ) {
        $queueConfig = config('webhooks.queue', []);
        $this->onConnection($queueConfig['connection'] ?? null);
        $this->onQueue($queueConfig['name'] ?? 'webhooks');
    }

    public function handle(
        WebhookRepository $repository,
        SignatureGenerator $signer,
        RetryStrategy $retryStrategy,
        CircuitBreaker $circuitBreaker,
    ): void {
        $event = $repository->findEvent($this->eventId);

        if ($event === null || $event->isDelivered()) {
            return;
        }

        $endpoint = $event->endpoint;

        if ($endpoint === null || ! $endpoint->is_active) {
            $repository->updateEvent($event, ['status' => WebhookEvent::STATUS_FAILED]);

            return;
        }

        if (! $this->force && ! $circuitBreaker->isAvailable($endpoint)) {
            $repository->updateEvent($event, [
                'status' => WebhookEvent::STATUS_PENDING,
                'next_retry_at' => Carbon::now()->addSeconds(
                    (int) config('webhooks.circuit_breaker.cooldown_seconds', 300)
                ),
            ]);

            return;
        }

        // Rate limiting check
        if (config('webhooks.rate_limiting.enabled', false)) {
            $limit = $endpoint->rate_limit_per_minute ?? (int) config('webhooks.rate_limiting.default_per_minute', 60);
            $key = "webhooker:rate:{$endpoint->id}";

            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $retryAfter = RateLimiter::availableIn($key);
                static::dispatch($this->eventId, $this->force)->delay(Carbon::now()->addSeconds($retryAfter));

                return;
            }

            RateLimiter::hit($key, 60);
        }

        $repository->updateEvent($event, ['status' => WebhookEvent::STATUS_PROCESSING]);

        WebhookSending::dispatch($event, $endpoint);

        $payload = json_encode($event->payload);
        $signature = $signer->generate($payload, $endpoint->secret);
        $signatureHeader = config('webhooks.signature_header', 'X-Webhook-Signature');
        $timeout = $endpoint->timeout_seconds ?: config('webhooks.timeout', 30);

        $headers = [
            'Content-Type' => 'application/json',
            $signatureHeader => $signature,
        ];

        $startTime = microtime(true);
        $responseStatus = null;
        $responseBody = null;
        $errorMessage = null;

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->withBody($payload, 'application/json')
                ->post($endpoint->url);

            $responseStatus = $response->status();
            $responseBody = config('webhooks.store_response_body', true)
                ? $response->body()
                : null;
        } catch (ConnectionException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $attemptNumber = $event->attempts_count + 1;

        if (config('webhooks.debug.enabled', false)) {
            $debugLogger = new WebhookLogger;
            $debugContext = [
                'event_id' => $event->id,
                'endpoint' => $endpoint->route_token,
                'url' => $endpoint->url,
                'duration_ms' => $durationMs,
                'response_status' => $responseStatus,
                'error' => $errorMessage,
            ];
            if (config('webhooks.debug.log_full_payload', false)) {
                $debugContext['payload'] = $event->payload;
            }
            if (config('webhooks.debug.log_full_headers', false)) {
                $debugContext['request_headers'] = $headers;
            }
            if (config('webhooks.debug.log_full_response_body', false)) {
                $debugContext['response_body'] = $responseBody;
            }
            $debugLogger->debug('Webhook request cycle', $debugContext);
        }

        $attempt = $repository->createAttempt([
            'event_id' => $event->id,
            'request_headers' => config('webhooks.log_request_headers', false) ? $headers : null,
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'attempted_at' => Carbon::now(),
        ]);

        $isSuccess = $responseStatus !== null && $responseStatus >= 200 && $responseStatus < 300;

        if ($isSuccess) {
            $circuitBreaker->recordSuccess($endpoint);

            $repository->updateEvent($event, [
                'status' => WebhookEvent::STATUS_DELIVERED,
                'attempts_count' => $attemptNumber,
                'last_attempt_at' => Carbon::now(),
                'next_retry_at' => null,
            ]);

            $logger = new WebhookLogger;
            $logger->info('Webhook delivered', [
                'event_id' => $event->id,
                'endpoint' => $endpoint->route_token,
                'status' => $responseStatus,
                'duration_ms' => $durationMs,
            ]);

            WebhookSent::dispatch($event->fresh(), $endpoint, $attempt);

            return;
        }

        $circuitBreaker->recordFailure($endpoint);

        $logger = new WebhookLogger;

        WebhookFailed::dispatch($event->fresh(), $endpoint, $attempt);

        $effectiveStrategy = $this->resolveRetryStrategy($endpoint, $retryStrategy);

        if ($effectiveStrategy->shouldRetry($attemptNumber)) {
            $nextRetry = $effectiveStrategy->nextRetry($attemptNumber);
            $repository->updateEvent($event, [
                'status' => WebhookEvent::STATUS_PENDING,
                'attempts_count' => $attemptNumber,
                'last_attempt_at' => Carbon::now(),
                'next_retry_at' => $nextRetry,
            ]);

            $logger->warning('Webhook delivery failed, scheduling retry', [
                'event_id' => $event->id,
                'endpoint' => $endpoint->route_token,
                'attempt' => $attemptNumber,
                'next_retry_at' => $nextRetry->toIso8601String(),
                'error' => $errorMessage,
            ]);

            static::dispatch($this->eventId)->delay($nextRetry);
        } else {
            $repository->updateEvent($event, [
                'status' => WebhookEvent::STATUS_FAILED,
                'attempts_count' => $attemptNumber,
                'last_attempt_at' => Carbon::now(),
                'next_retry_at' => null,
            ]);

            $logger->error('Webhook delivery permanently failed', [
                'event_id' => $event->id,
                'endpoint' => $endpoint->route_token,
                'total_attempts' => $attemptNumber,
                'error' => $errorMessage,
            ]);

            WebhookRetriesExhausted::dispatch($event->fresh(), $endpoint);
        }
    }

    /**
     * Resolve the effective retry strategy for the endpoint.
     *
     * Per-endpoint config takes precedence over the global strategy.
     */
    private function resolveRetryStrategy(
        \TechraysLabs\Webhooker\Models\WebhookEndpoint $endpoint,
        RetryStrategy $globalStrategy,
    ): RetryStrategy {
        // If endpoint has a custom strategy class, use it
        if ($endpoint->retry_strategy !== null) {
            $strategyClass = $endpoint->retry_strategy;

            if (class_exists($strategyClass) && is_subclass_of($strategyClass, RetryStrategy::class)) {
                return app($strategyClass);
            }
        }

        // If endpoint has custom max_retries, wrap default strategy with that limit
        if ($endpoint->max_retries !== null) {
            $config = config('webhooks.retry', []);

            return new ExponentialBackoffRetry(
                maxAttempts: $endpoint->max_retries,
                baseDelaySeconds: $config['base_delay_seconds'] ?? 10,
                multiplier: $config['multiplier'] ?? 2,
            );
        }

        return $globalStrategy;
    }
}
