# Laravel Webhooker

A Laravel-native Webhook Reliability Engine for outbound and inbound webhook management with intelligent retry, replay, and a built-in dashboard.

## The Problem

Webhooks are critical infrastructure, but building reliable webhook delivery is hard. You need retry logic, signature verification, attempt logging, failure recovery, and operational visibility. Most teams either build fragile one-off solutions or rely on external SaaS services.

**Laravel Webhooker** gives you production-grade webhook infrastructure as a Composer package. Zero external dependencies. No SaaS billing. Just install, configure, and ship.

## Features

- **Outbound Delivery** - Queue-based async delivery with configurable timeout
- **Inbound Handling** - Receive, verify, deduplicate, and process inbound webhooks
- **Exponential Backoff Retry** - Configurable max attempts, delay, and multiplier
- **HMAC Signatures** - Automatic signing for outbound, verification for inbound
- **Attempt Logging** - Full record of every delivery attempt with status, duration, and errors
- **Replay** - Re-dispatch any failed event via Artisan command
- **Pruning** - Automatic retention management to prevent unbounded storage growth
- **Dashboard** - Blade-based UI for event inspection, filtering, and attempt review
- **Extensible** - Interface-driven architecture ready for custom implementations

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require techrays-labs/laravel-webhooker
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=webhooker-config
```

Run the migrations:

```bash
php artisan migrate
```

## Quick Start: Outbound Webhooks

### Register an endpoint

```php
use TechRaysLabs\Webhooker\Webhooker;

$webhooker = app(Webhooker::class);

$endpoint = $webhooker->registerEndpoint([
    'name' => 'Payment Service',
    'url' => 'https://payments.example.com/webhook',
    'direction' => 'outbound',
    'secret' => 'your-webhook-secret',
    'is_active' => true,
    'timeout_seconds' => 30,
]);
```

### Dispatch an event

```php
$webhooker->dispatch($endpoint->id, 'order.created', [
    'order_id' => 12345,
    'total' => 99.99,
    'currency' => 'USD',
]);
```

### Broadcast to all active outbound endpoints

```php
$webhooker->broadcast('order.shipped', [
    'order_id' => 12345,
    'tracking_number' => 'ABC123',
]);
```

The event is persisted immediately and delivered asynchronously via your queue. If delivery fails, it retries automatically with exponential backoff.

## Quick Start: Inbound Webhooks

### Register an inbound endpoint

```php
$endpoint = $webhooker->registerEndpoint([
    'name' => 'Stripe Webhooks',
    'url' => 'https://yourapp.com/api/webhooks/inbound/' . $id,
    'direction' => 'inbound',
    'secret' => 'whsec_your_stripe_secret',
    'is_active' => true,
    'timeout_seconds' => 30,
]);
```

Inbound webhooks are received at:

```
POST /api/webhooks/inbound/{endpoint_id}
```

The package automatically:
1. Verifies the HMAC signature via the `X-Webhook-Signature` header
2. Rejects invalid or missing signatures with `401`
3. Deduplicates events via the `X-Webhook-Event-ID` header
4. Persists the payload
5. Queues it for async processing

### Custom inbound processing

Bind your own processor in a service provider:

```php
use TechRaysLabs\Webhooker\Contracts\InboundProcessor;

$this->app->bind(InboundProcessor::class, MyStripeProcessor::class);
```

```php
use TechRaysLabs\Webhooker\Contracts\InboundProcessor;
use TechRaysLabs\Webhooker\Models\WebhookEvent;

class MyStripeProcessor implements InboundProcessor
{
    public function process(WebhookEvent $event): bool
    {
        $payload = $event->payload;

        match ($event->event_name) {
            'payment_intent.succeeded' => $this->handlePayment($payload),
            'customer.subscription.deleted' => $this->handleCancellation($payload),
            default => null,
        };

        return true;
    }
}
```

## Dashboard

The built-in dashboard provides operational visibility into your webhook events.

Access it at `/webhooks` (configurable prefix).

The dashboard includes:
- Event listing with status, endpoint, and attempt count
- Filtering by status, endpoint, and event name
- Event detail view with payload and delivery attempt timeline
- Endpoint listing

### Authorization

The dashboard is protected by a Laravel Gate. Define it in your `AuthServiceProvider`:

```php
Gate::define('viewWebhookDashboard', function ($user) {
    return $user->isAdmin();
});
```

### Disable the dashboard

```php
// config/webhooks.php
'dashboard' => [
    'enabled' => false,
],
```

## Retention & Pruning

By default, webhook events are retained for 30 days. Run the prune command regularly:

```bash
php artisan webhook:prune
```

Or with a custom retention period:

```bash
php artisan webhook:prune --days=7
```

Schedule it in your `Console/Kernel.php`:

```php
$schedule->command('webhook:prune')->daily();
```

## Retry Configuration

The default retry strategy uses exponential backoff:

```php
// config/webhooks.php
'retry' => [
    'max_attempts' => 5,        // Total attempts before marking as failed
    'base_delay_seconds' => 10, // Initial delay
    'multiplier' => 2,          // Exponential multiplier
],
```

This produces delays of: 10s, 20s, 40s, 80s, 160s.

### Custom retry strategy

Implement the `RetryStrategy` contract and bind it in your service provider:

```php
use TechRaysLabs\Webhooker\Contracts\RetryStrategy;

$this->app->bind(RetryStrategy::class, MyCustomRetryStrategy::class);
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `webhook:prune` | Delete events older than retention period |
| `webhook:replay {event_id}` | Re-dispatch a failed event |
| `webhook:endpoint:list` | List all registered endpoints |

## Configuration Reference

```php
// config/webhooks.php
return [
    'retry' => [
        'max_attempts' => 5,
        'base_delay_seconds' => 10,
        'multiplier' => 2,
    ],
    'timeout' => 30,
    'signing_algorithm' => 'sha256',
    'signature_header' => 'X-Webhook-Signature',
    'queue' => [
        'connection' => null,  // null = default connection
        'name' => 'webhooks',
    ],
    'retention_days' => 30,
    'store_response_body' => true,
    'log_request_headers' => false,
    'dashboard' => [
        'enabled' => true,
        'prefix' => 'webhooks',
        'middleware' => ['web', 'auth'],
        'gate' => 'viewWebhookDashboard',
    ],
];
```

## Roadmap

### v1.0 (Current Focus)
- [x] Outbound webhook delivery with retry
- [x] Inbound webhook reception with verification
- [x] HMAC signature generation/verification
- [x] Queue-based async delivery
- [x] Exponential backoff retry
- [x] Attempt logging
- [x] Event replay
- [x] Pruning
- [x] Blade dashboard
- [ ] Laravel Pint formatting
- [ ] PHPStan static analysis

### v2.0 (Future)
- Storage driver abstraction
- Table partitioning support
- Dead-letter queue
- Per-endpoint retry strategies
- Endpoint health scoring
- Event batching
- Multi-database support

## Contributing

Contributions are welcome. Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`feature/your-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`vendor/bin/phpunit`)
5. Use conventional commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`)
6. Submit a pull request

## License

MIT License. See [LICENSE](LICENSE) for details.
