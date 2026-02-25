# Laravel Webhooker

A Laravel-native Webhook Reliability Engine for outbound and inbound webhook management with intelligent retry, replay, circuit breaker, and a built-in dashboard.

## The Problem

Webhooks are critical infrastructure, but building reliable webhook delivery is hard. You need retry logic, signature verification, attempt logging, failure recovery, and operational visibility. Most teams either build fragile one-off solutions or rely on external SaaS services.

**Laravel Webhooker** gives you production-grade webhook infrastructure as a Composer package. Zero external dependencies. No SaaS billing. Just install, configure, and ship.

## Features

### Core

- **Outbound Delivery** - Queue-based async delivery with configurable timeout
- **Inbound Handling** - Receive, verify, deduplicate, and process inbound webhooks
- **Exponential Backoff Retry** - Configurable max attempts, delay, and multiplier
- **HMAC Signatures** - Automatic signing for outbound, verification for inbound
- **Attempt Logging** - Full record of every delivery attempt with status, duration, and errors
- **Replay** - Re-dispatch any failed event via Artisan command or dashboard
- **Pruning** - Automatic retention management to prevent unbounded storage growth

### Observability

- **Webhook Metrics** - Aggregated stats (success rate, response time, failure rate) via a `WebhookMetrics` service
- **Endpoint Health Status** - Computed health classification (healthy, degraded, failing, unknown) per endpoint
- **Laravel Events** - Native event dispatching at key lifecycle points for custom listeners
- **Structured Logging** - Optional dedicated log channel with structured context

### Control

- **Circuit Breaker** - Auto-disable failing endpoints, cooldown period, half-open recovery
- **Endpoint Tagging** - Tag/group endpoints, dispatch to all endpoints by tag
- **Enable/Disable API** - Programmatically toggle endpoints with reason tracking
- **Per-Endpoint Retry** - Override global retry settings per endpoint

### Developer UX

- **Testing Facade** - `Webhook::fake()` with assertion helpers for your test suite
- **Inbound Simulator** - Artisan command to simulate inbound webhook deliveries locally
- **Debug Mode** - Verbose logging of full request/response cycles in development
- **Tinker Helpers** - `inspect()`, `lastFailed()`, `retryLast()`, `stats()` on the facade

### Hardening

- **Rate Limiting** - Per-endpoint outbound rate limits using Laravel's RateLimiter
- **Payload Validation** - Schema-based validation of outbound payloads before dispatch
- **IP Allowlist** - Restrict inbound webhooks to known IP addresses (supports CIDR)
- **Secret Rotation** - Rotate endpoint secrets with a configurable grace period
- **Idempotency Keys** - Prevent duplicate outbound deliveries

### Scaling & Reliability (v2.0)

- **Storage Driver Abstraction** - Pluggable storage backends via Laravel's Manager pattern
- **Dead-Letter Queue** - Auto-capture events that exhaust all retries for inspection and manual retry
- **Event Batching** - Dispatch to multiple endpoints as a tracked batch with progress monitoring
- **Endpoint Health History** - Periodic health snapshots for trend analysis over time
- **Multi-Database Support** - Read/write connection separation for read replicas
- **Table Partitioning** - MySQL (RANGE) and PostgreSQL (declarative) partitioning for high-volume tables
- **Horizontal Scaling** - Distributed locking for safe multi-worker webhook processing

### Dashboard

- **Stats Overview** - 24h event counts, success rate, average response time, endpoint health breakdown
- **Event Timeline** - Visual timeline of delivery attempts with status codes, durations, and errors
- **Bulk Actions** - Select and replay or delete multiple events at once
- **Endpoint Detail Page** - Config, health, circuit breaker state, 7-day sparkline
- **Tag Filtering** - Filter events by endpoint tag
- **Dark Mode** - CSS variable theming with `prefers-color-scheme` and manual toggle

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
use TechraysLabs\Webhooker\Facades\Webhook;

$endpoint = Webhook::registerEndpoint([
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
Webhook::dispatch($endpoint->id, 'order.created', [
    'order_id' => 12345,
    'total' => 99.99,
    'currency' => 'USD',
]);
```

### Dispatch with idempotency key

```php
Webhook::dispatch($endpoint->id, 'order.created', $payload, [
    'idempotency_key' => 'order-123-created',
]);
```

### Broadcast to all active outbound endpoints

```php
Webhook::broadcast('order.shipped', [
    'order_id' => 12345,
    'tracking_number' => 'ABC123',
]);
```

### Dispatch to tagged endpoints

```php
Webhook::dispatchToTag('payments', 'order.created', $payload);
```

The event is persisted immediately and delivered asynchronously via your queue. If delivery fails, it retries automatically with exponential backoff.

## Quick Start: Inbound Webhooks

### Register an inbound endpoint

```php
$endpoint = Webhook::registerEndpoint([
    'name' => 'Stripe Webhooks',
    'url' => 'https://yourapp.com/api/webhooks/inbound',
    'direction' => 'inbound',
    'secret' => 'whsec_your_stripe_secret',
    'is_active' => true,
    'timeout_seconds' => 30,
]);
```

Inbound webhooks are received at:

```
POST /api/webhooks/inbound/{route_token}
```

Each endpoint gets a unique `route_token` (e.g., `ep_a8f3kx9m2b7q`) auto-generated on creation. The full URL is displayed in the dashboard for easy copy-paste.

The package automatically:

1. Checks IP allowlist (if enabled)
2. Verifies the HMAC signature via the `X-Webhook-Signature` header
3. Rejects invalid or missing signatures with `401`
4. Deduplicates events via the `X-Webhook-Event-ID` header
5. Persists the payload
6. Queues it for async processing

### Custom inbound processing

Bind your own processor in a service provider:

```php
use TechraysLabs\Webhooker\Contracts\InboundProcessor;

$this->app->bind(InboundProcessor::class, MyStripeProcessor::class);
```

```php
use TechraysLabs\Webhooker\Contracts\InboundProcessor;
use TechraysLabs\Webhooker\Models\WebhookEvent;

class MyStripeProcessor implements InboundProcessor
{
    public function process(WebhookEvent $event): bool
    {
        match ($event->event_name) {
            'payment_intent.succeeded' => $this->handlePayment($event->payload),
            'customer.subscription.deleted' => $this->handleCancellation($event->payload),
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

- **Stats overview** - Total events, delivered, failed, pending, success rate, avg response time, endpoint health breakdown, active circuit breakers
- **Event list** - Filterable by status, endpoint, event name, and tag with bulk actions (replay, delete)
- **Event detail** - Payload preview, delivery timeline with color-coded status, expandable response bodies, replay button
- **Endpoint list** - Route tokens, URLs, direction, active/disabled status, tags, inbound URLs
- **Endpoint detail** - Configuration, health status, circuit breaker state, 7-day success rate sparkline, recent events
- **Dark mode** - Toggle via button, respects system preference, persists via cookie

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

## Circuit Breaker

The circuit breaker prevents wasting queue resources on consistently failing endpoints.

**States:**
- `CLOSED` - Endpoint is healthy, deliveries proceed normally
- `OPEN` - Endpoint has failed too many times, deliveries are paused
- `HALF_OPEN` - After cooldown, allows a test delivery to check recovery

```php
// config/webhooks.php
'circuit_breaker' => [
    'enabled' => true,
    'failure_threshold' => 10,    // Consecutive failures to trip
    'cooldown_seconds' => 300,    // Wait before half-open test
    'success_threshold' => 2,     // Successes in half-open to close
],
```

The circuit breaker can be bypassed with `--force` on the replay command.

## Endpoint Management

### Enable/Disable

```php
Webhook::disable($endpointId, 'Scheduled maintenance');
Webhook::enable($endpointId);
Webhook::isEnabled($endpointId);
```

### Tagging

```php
$endpoint->attachTag('payments');
$endpoint->detachTag('payments');
$endpoint->hasTag('payments');

// Dispatch to all endpoints with a tag
Webhook::dispatchToTag('payments', 'order.created', $payload);
```

### Secret Rotation

```php
$newSecret = Webhook::rotateSecret($endpointId);
```

After rotation, both old and new secrets are accepted during a configurable grace period (default: 24 hours). Expired previous secrets are cleaned up by the `webhook:secret:cleanup` command.

## Retention & Pruning

By default, webhook events are retained for 30 days. Run the prune command regularly:

```bash
php artisan webhook:prune
```

Or with a custom retention period:

```bash
php artisan webhook:prune --days=7
```

Schedule it in your application:

```php
$schedule->command('webhook:prune')->daily();
$schedule->command('webhook:secret:cleanup')->hourly();
```

## Retry Configuration

The default retry strategy uses exponential backoff:

```php
// config/webhooks.php
'retry' => [
    'max_attempts' => 5,
    'base_delay_seconds' => 10,
    'multiplier' => 2,
],
```

This produces delays of: 10s, 20s, 40s, 80s, 160s.

### Per-endpoint retry

```php
$endpoint->max_retries = 10;
$endpoint->retry_strategy = MyCustomRetryStrategy::class;
$endpoint->save();
```

### Custom retry strategy

Implement the `RetryStrategy` contract and bind it in your service provider:

```php
use TechraysLabs\Webhooker\Contracts\RetryStrategy;

$this->app->bind(RetryStrategy::class, MyCustomRetryStrategy::class);
```

## Rate Limiting

Prevent overwhelming destination servers:

```php
// config/webhooks.php
'rate_limiting' => [
    'enabled' => false,
    'default_per_minute' => 60,
],
```

Per-endpoint override via the `rate_limit_per_minute` column on `webhook_endpoints`.

## Payload Validation

Validate outbound payloads before dispatch:

```php
// config/webhooks.php
'payload_validation' => [
    'enabled' => false,
    'schemas' => [
        'order.created' => [
            'order_id' => 'required|integer',
            'amount' => 'required|numeric',
            'currency' => 'required|string|size:3',
        ],
    ],
],
```

Invalid payloads throw `InvalidWebhookPayloadException`.

## IP Allowlist (Inbound)

Restrict inbound webhooks to known IPs:

```php
// config/webhooks.php
'inbound' => [
    'ip_allowlist' => [
        'enabled' => false,
        'global' => ['192.168.1.0/24'],
        'trust_proxy' => false,
    ],
],
```

Per-endpoint allowlists can be set via the `allowed_ips` JSON column.

## Storage Driver Abstraction

Laravel Webhooker uses a pluggable storage layer based on Laravel's Manager pattern. The default driver is `eloquent`.

```php
// config/webhooks.php
'storage' => [
    'driver' => env('WEBHOOK_STORAGE_DRIVER', 'eloquent'),

    'drivers' => [
        'eloquent' => [
            'connection' => null,
            'read_connection' => null,
        ],
    ],
],
```

### Custom storage drivers

Extend the `WebhookStorageManager` to register your own drivers:

```php
use TechraysLabs\Webhooker\Storage\WebhookStorageManager;

app(WebhookStorageManager::class)->extend('dynamodb', function ($app) {
    return new DynamoDbWebhookRepository(config('webhooks.storage.drivers.dynamodb'));
});
```

## Dead-Letter Queue

Events that exhaust all retries can be automatically moved to a dead-letter queue for inspection and manual retry.

```php
// config/webhooks.php
'dead_letter' => [
    'enabled' => false,
    'auto_move' => true,        // Auto-move after retries exhausted
    'retention_days' => 90,     // DLQ retention before pruning
],
```

### Managing the DLQ

```bash
# List dead-lettered events
php artisan webhook:dead-letter list

# Retry a specific dead-lettered event
php artisan webhook:dead-letter retry {event_id}

# Purge all dead-lettered events older than retention
php artisan webhook:dead-letter purge

# Count dead-lettered events
php artisan webhook:dead-letter count
```

The `WebhookMovedToDeadLetter` event is fired when an event enters the DLQ.

## Event Batching

Dispatch the same event to multiple endpoints as a tracked batch:

```php
use TechraysLabs\Webhooker\Facades\Webhook;

// Dispatch to specific endpoints
$batch = Webhook::dispatchBatch([1, 2, 3], 'order.created', [
    'order_id' => 12345,
    'total' => 99.99,
]);

// Broadcast to all active outbound endpoints as a batch
$batch = Webhook::broadcastBatch('order.shipped', [
    'order_id' => 12345,
    'tracking_number' => 'ABC123',
]);

// Check batch progress
$batch = Webhook::batchStatus($batch->id);
echo $batch->status;          // pending, processing, completed, partial_failure, failed
echo $batch->success_count;
echo $batch->failure_count;
```

```php
// config/webhooks.php
'batching' => [
    'enabled' => true,
    'max_batch_size' => 1000,
    'allow_partial_failure' => true,
],
```

## Endpoint Health History

Capture periodic health snapshots for trend analysis:

```php
// config/webhooks.php
'health_history' => [
    'enabled' => false,
    'snapshot_interval' => 60,   // Minutes between snapshots
    'retention_days' => 90,
],
```

Schedule the snapshot command:

```php
$schedule->command('webhook:health:snapshot')->hourly();
```

Access health history programmatically:

```php
use TechraysLabs\Webhooker\Contracts\WebhookMetrics;

$metrics = app(WebhookMetrics::class);
$history = $metrics->endpointHealthHistory($endpointId, days: 30);

foreach ($history as $point) {
    echo "{$point->date}: {$point->successRate}% ({$point->status})";
}
```

## Multi-Database Support

Route reads to a replica and writes to the primary database:

```php
// config/webhooks.php
'storage' => [
    'driver' => 'eloquent',

    'drivers' => [
        'eloquent' => [
            'connection' => 'mysql',              // Primary for writes
            'read_connection' => 'mysql-replica',  // Replica for reads
        ],
    ],
],
```

All repository queries are automatically routed to the correct connection.

## Table Partitioning

For high-volume installations, partition the `webhook_events` and `webhook_attempts` tables:

```php
// config/webhooks.php
'partitioning' => [
    'enabled' => false,
    'strategy' => 'monthly',
    'tables' => ['webhook_events', 'webhook_attempts'],
    'future_partitions' => 3,
],
```

Publish the partition migration stubs:

```bash
php artisan vendor:publish --tag=webhooker-stubs
```

Manage partitions via Artisan:

```bash
# Create future partitions
php artisan webhook:partition:create

# Drop old partitions
php artisan webhook:partition:drop --before=2025-01
```

Supports MySQL (RANGE partitioning) and PostgreSQL (declarative partitioning).

## Horizontal Scaling

For multi-worker deployments, enable distributed locking to prevent duplicate event processing:

```php
// config/webhooks.php
'scaling' => [
    'enabled' => false,
    'lock_driver' => 'cache',
    'lock_ttl' => 300,           // Lock timeout in seconds
    'unique_jobs' => true,
],
```

When enabled, each webhook job acquires a distributed lock before processing. This ensures that even with multiple queue workers, each event is processed exactly once.

You can provide your own lock implementation by binding the `WebhookLock` contract:

```php
use TechraysLabs\Webhooker\Contracts\WebhookLock;

$this->app->bind(WebhookLock::class, MyRedisLockProvider::class);
```

## Testing

Use the testing facade in your application's test suite:

```php
use TechraysLabs\Webhooker\Facades\Webhook;

Webhook::fake();

// ... trigger your application code ...

Webhook::assertDispatched('order.created');
Webhook::assertDispatched('order.created', function ($event) {
    return $event->payload['order_id'] === 123;
});
Webhook::assertNothingDispatched();
Webhook::assertDispatchedTimes('order.created', 3);
```

Or use the trait:

```php
use TechraysLabs\Webhooker\Testing\InteractsWithWebhooks;

class MyTest extends TestCase
{
    use InteractsWithWebhooks;

    public function test_something(): void
    {
        $fake = $this->fakeWebhooks();
        // ... trigger your app code ...
        $fake->assertDispatched('order.created');
    }
}
```

## Debug Mode

Enable verbose logging in development:

```php
// config/webhooks.php
'debug' => [
    'enabled' => env('WEBHOOK_DEBUG', false),
    'log_full_payload' => false,
    'log_full_headers' => false,
    'log_full_response_body' => false,
],
```

A runtime warning is logged if debug mode is enabled in a production environment.

## Laravel Events

The package fires native Laravel events at key lifecycle points:

| Event | Fires When |
| --- | --- |
| `WebhookSending` | Before outbound HTTP call |
| `WebhookSent` | After successful delivery |
| `WebhookFailed` | After a failed attempt |
| `WebhookRetriesExhausted` | All retries used up |
| `WebhookReplayRequested` | Replay triggered |
| `InboundWebhookReceived` | Inbound payload arrives |
| `InboundWebhookProcessed` | Inbound processing succeeds |
| `InboundWebhookFailed` | Inbound processing fails |
| `EndpointDisabled` | Endpoint disabled |
| `EndpointEnabled` | Endpoint re-enabled |
| `EndpointCircuitOpened` | Circuit breaker trips |
| `EndpointCircuitClosed` | Circuit breaker recovers |
| `EndpointSecretRotated` | Secret rotation completed |
| `WebhookMovedToDeadLetter` | Event moved to dead-letter queue |
| `WebhookBatchCompleted` | All events in a batch succeeded |
| `WebhookBatchPartiallyFailed` | Batch completed with mixed results |

## CLI Commands

| Command | Description |
| --- | --- |
| `webhook:prune` | Delete events older than retention period |
| `webhook:replay {event_id}` | Re-dispatch a single event |
| `webhook:replay --status= --endpoint=` | Bulk replay with filters |
| `webhook:endpoint:list` | List all registered endpoints |
| `webhook:endpoint:disable {id}` | Disable an endpoint |
| `webhook:endpoint:enable {id}` | Enable an endpoint |
| `webhook:health` | Show health status of all endpoints |
| `webhook:circuit:status` | Show circuit breaker states |
| `webhook:circuit:reset {endpoint_id}` | Reset circuit breaker |
| `webhook:simulate {type}` | Simulate inbound webhook delivery |
| `webhook:secret:rotate {endpoint_id}` | Rotate endpoint secret |
| `webhook:secret:cleanup` | Remove expired previous secrets |
| `webhook:dead-letter list\|retry\|purge\|count` | Manage dead-letter queue |
| `webhook:health:snapshot` | Capture endpoint health snapshots |
| `webhook:partition:create` | Create future table partitions |
| `webhook:partition:drop` | Drop old table partitions |

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
        'connection' => null,
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
        'max_bulk_size' => 100,
    ],
    'metrics' => [
        'cache_ttl' => 60,
        'healthy_threshold' => 95,
        'degraded_threshold' => 70,
    ],
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 10,
        'cooldown_seconds' => 300,
        'success_threshold' => 2,
    ],
    'logging' => [
        'channel' => env('WEBHOOK_LOG_CHANNEL', null),
        'log_payload' => false,
        'log_headers' => false,
        'log_level' => 'info',
    ],
    'debug' => [
        'enabled' => env('WEBHOOK_DEBUG', false),
        'log_full_payload' => false,
        'log_full_headers' => false,
        'log_full_response_body' => false,
    ],
    'rate_limiting' => [
        'enabled' => false,
        'default_per_minute' => 60,
    ],
    'payload_validation' => [
        'enabled' => false,
        'schemas' => [],
    ],
    'inbound' => [
        'ip_allowlist' => [
            'enabled' => false,
            'global' => [],
            'trust_proxy' => false,
        ],
    ],
    'secret_rotation' => [
        'grace_period_hours' => 24,
    ],
    'storage' => [
        'driver' => env('WEBHOOK_STORAGE_DRIVER', 'eloquent'),
        'drivers' => [
            'eloquent' => [
                'connection' => null,
                'read_connection' => null,
            ],
        ],
    ],
    'dead_letter' => [
        'enabled' => false,
        'auto_move' => true,
        'retention_days' => 90,
    ],
    'batching' => [
        'enabled' => true,
        'max_batch_size' => 1000,
        'allow_partial_failure' => true,
    ],
    'health_history' => [
        'enabled' => false,
        'snapshot_interval' => 60,
        'retention_days' => 90,
    ],
    'partitioning' => [
        'enabled' => false,
        'strategy' => 'monthly',
        'tables' => ['webhook_events', 'webhook_attempts'],
        'future_partitions' => 3,
    ],
    'scaling' => [
        'enabled' => false,
        'lock_driver' => 'cache',
        'lock_ttl' => 300,
        'unique_jobs' => true,
    ],
];
```

## Upgrade Guide

### Upgrading from v0.1.0 to v2.0.0

v2.0.0 introduces breaking changes to the `WebhookRepository` contract, `DispatchWebhookJob`, and `ProcessInboundWebhookJob`. If you have custom implementations of these contracts, you will need to update them.

1. Run the new migrations:

```bash
php artisan migrate
```

2. If you have a custom `WebhookRepository` implementation, add the new method signatures from the contract.

3. If you dispatch jobs manually (outside the facade), update `handle()` calls to include the `WebhookLock` parameter.

4. Review the new config sections added to `config/webhooks.php` and publish updated config if needed:

```bash
php artisan vendor:publish --tag=webhooker-config --force
```

## Roadmap

### v0.1.0 (Released)

Core webhook engine: outbound/inbound delivery, retry, signatures, circuit breaker, dashboard, tagging, rate limiting, payload validation, IP allowlist, secret rotation, idempotency, testing facade, debug mode.

### v2.0.0 (Released)

- Storage driver abstraction
- Dead-letter queue
- Event batching
- Endpoint health history
- Multi-database support
- Table partitioning support
- Horizontal scaling support

### Future

- Webhook event transforms (modify payload per endpoint)
- Webhook event filtering (subscribe endpoints to specific event patterns)
- GraphQL / REST API for programmatic management
- Webhook delivery analytics dashboard
- Webhook event schema registry
- Real-time delivery monitoring via WebSockets
- Plugin system for third-party integrations

## Contributing

Contributions are welcome. Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`feature/your-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`vendor/bin/phpunit`)
5. Format code with Laravel Pint (`vendor/bin/pint`)
6. Use conventional commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`)
7. Submit a pull request

## License

MIT License. See [LICENSE](LICENSE) for details.
