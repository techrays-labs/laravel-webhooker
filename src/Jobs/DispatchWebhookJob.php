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
use TechraysLabs\Webhooker\Contracts\RetryStrategy;
use TechraysLabs\Webhooker\Contracts\SignatureGenerator;
use TechraysLabs\Webhooker\Contracts\WebhookRepository;
use TechraysLabs\Webhooker\Events\WebhookFailed;
use TechraysLabs\Webhooker\Events\WebhookRetriesExhausted;
use TechraysLabs\Webhooker\Events\WebhookSending;
use TechraysLabs\Webhooker\Events\WebhookSent;
use TechraysLabs\Webhooker\Models\WebhookEvent;
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
    ) {
        $queueConfig = config('webhooks.queue', []);
        $this->onConnection($queueConfig['connection'] ?? null);
        $this->onQueue($queueConfig['name'] ?? 'webhooks');
    }

    public function handle(
        WebhookRepository $repository,
        SignatureGenerator $signer,
        RetryStrategy $retryStrategy,
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

        $logger = new WebhookLogger;

        WebhookFailed::dispatch($event->fresh(), $endpoint, $attempt);

        if ($retryStrategy->shouldRetry($attemptNumber)) {
            $nextRetry = $retryStrategy->nextRetry($attemptNumber);
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
}
