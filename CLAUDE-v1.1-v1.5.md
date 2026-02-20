# Laravel Webhook Engine – Feature Set v1.1–v1.5 (Internal Milestones)

> **Context**: This document defines internal development milestones v1.1 through v1.5. These are **not public versions** — they are internal planning labels only. The first public release will be `v0.1.0`, which includes everything from `CLAUDE.md` (internal v1.0) plus everything in this document (internal v1.1–v1.5). All development rules, commit strategy, testing requirements, and architecture constraints from `CLAUDE.md` still apply. Read `CLAUDE.md` first — this document extends it.

---

## Guiding Principle

Every feature in this document must:

- Be backward-compatible with v1.0
- Not require users to re-migrate unless a new migration is provided
- Follow the same contracts/repository architecture
- Ship with tests (90% coverage rule still applies)
- Be individually releasable as a minor or patch version

### Inbound Routing Reminder

Starting from v1.1.0, all inbound webhook endpoints use **alphanumeric route tokens** (`ep_a8f3kx9m2b7q`) in URLs — never sequential integer IDs. This is the first feature implemented in v1.1.0 (section 1.0). Any feature in v1.1.0+ that references inbound endpoints in URLs, CLI commands, or user-facing output must use `route_token`. Internal relationships (foreign keys, queries) still use the integer `id`.

---

## Internal Milestone Targets

> These are internal development milestones, not public releases. All ship together as public `v0.1.0`.

| Milestone | Codename         | Focus Area                          |
| --------- | ---------------- | ----------------------------------- |
| v1.1      | **Observability** | Route tokens, events, metrics, health monitoring |
| v1.2      | **Control**       | Endpoint management & circuit breaker |
| v1.3      | **Developer UX**  | Testing utilities, debugging tools |
| v1.4      | **Hardening**     | Security, rate limiting, validation |
| v1.5      | **Dashboard v2**  | Enhanced UI, real-time stats, bulk actions |

---

## v1.1.0 — Observability

### 1.0 Inbound Route Tokens (Security Fix)

**Problem:** v1.0 uses sequential integer IDs in inbound webhook URLs (e.g., `/webhooks/inbound/3`). These are guessable, enumerable, and leak information about system size and creation order. This must be fixed before any other v1.1 work.

**Solution:** Add an alphanumeric `route_token` to every endpoint and use it for all inbound URL routing.

**URL format (after migration):**

```
# Before (v1.0 — insecure)
POST /webhooks/inbound/3

# After (v1.1+ — secure)
POST /webhooks/inbound/ep_a8f3kx9m2b7q
```

**Schema addition (new migration):**

- Add `route_token` string column to `webhook_endpoints` (unique, indexed)

**Token generation rules:**

- Prefix: `ep_` (for "endpoint")
- Body: 12+ cryptographically random alphanumeric characters (`a-z`, `0-9`)
- Generated automatically on endpoint creation via `Str::random()` or equivalent
- Immutable after creation — never changes
- Total length: 15 characters minimum (`ep_` + 12 random)

**Migration behavior for existing data:**

- The migration must auto-generate `route_token` for all existing endpoints that don't have one
- Use a migration like:

```php
// Add column
$table->string('route_token')->unique()->nullable();

// Then in a second step, backfill existing rows
WebhookEndpoint::whereNull('route_token')->each(function ($endpoint) {
    $endpoint->update(['route_token' => 'ep_' . Str::random(12)]);
});

// Then make non-nullable
$table->string('route_token')->unique()->nullable(false)->change();
```

**Routing rules:**

- Inbound controller must resolve endpoints by `route_token`, not by `id`
- `id` remains the internal primary key for all relationships and foreign keys
- Return `404 Not Found` for unknown tokens (not `403` — avoid information leakage)
- Old integer-based routes must be removed or return `404`

**Model changes:**

- Add `route_token` to `$fillable` (auto-generated, not user-editable)
- Add `getRouteKeyName()` override returning `'route_token'` for route model binding
- Add boot method to auto-generate token on `creating` event

**CLI impact:**

- `webhook:endpoint:list` must display `route_token` column
- Any CLI command that previously accepted endpoint ID for inbound should now accept `route_token`

**Dashboard impact:**

- Show `route_token` (not internal `id`) as the endpoint identifier in the UI
- Display the full inbound URL with token for easy copy-paste
- Never expose the internal integer `id` to users

**Tests required:**

- Token is auto-generated on endpoint creation
- Token is unique across endpoints
- Inbound route resolves correctly by token
- Unknown token returns 404
- Old integer route returns 404
- Existing endpoints get backfilled tokens during migration

---

### 1.1 Laravel Event Dispatching

Dispatch native Laravel events at key lifecycle points so users can hook into them with listeners.

Events to implement:

```php
WebhookSending::class        // Before outbound HTTP call
WebhookSent::class           // After successful delivery
WebhookFailed::class         // After a failed attempt
WebhookRetriesExhausted::class // After all retries are spent
WebhookReplayRequested::class  // When replay is triggered
InboundWebhookReceived::class  // When inbound payload arrives
InboundWebhookProcessed::class // After inbound processing completes
InboundWebhookFailed::class    // When inbound processing fails
EndpointDisabled::class        // When an endpoint is auto-disabled
```

Rules:

- Every event must be a simple data class implementing `ShouldBroadcast` optionally
- Events must carry the relevant model (event, endpoint, attempt) as public properties
- Events must not contain secrets — mask `secret` field before dispatching
- All events must be in `Events/` namespace
- Write a test for each event confirming it fires at the correct lifecycle point

### 1.2 Webhook Metrics Collector

Provide a `WebhookMetrics` service that returns aggregated stats. This powers the dashboard and can be consumed by users for custom monitoring.

```php
interface WebhookMetrics
{
    public function summary(string $direction, ?Carbon $from, ?Carbon $to): MetricsSummary;
    public function endpointHealth(int $endpointId): EndpointHealth;
    public function failureRate(string $direction, ?Carbon $from, ?Carbon $to): float;
    public function averageResponseTime(int $endpointId, ?Carbon $from, ?Carbon $to): float;
}
```

`MetricsSummary` DTO must include:

- Total events
- Successful count
- Failed count
- Pending count
- Average attempts per event
- Average response time (ms)

`EndpointHealth` DTO must include:

- Endpoint ID & name
- Success rate (percentage)
- Average response time
- Last successful delivery timestamp
- Last failure timestamp
- Current status (`healthy`, `degraded`, `failing`)

Rules:

- All queries must use indexed columns
- Results must be cacheable (use Laravel cache with configurable TTL)
- Must not run raw queries — use the repository layer
- Add config key: `webhook-engine.metrics.cache_ttl` (default: 60 seconds)

### 1.3 Endpoint Health Status

Add a computed `health_status` to endpoints based on recent delivery history.

Health classification logic:

| Condition                              | Status       |
| -------------------------------------- | ------------ |
| ≥ 95% success rate in last 100 events  | `healthy`    |
| 70–94% success rate in last 100 events | `degraded`   |
| < 70% success rate in last 100 events  | `failing`    |
| No events in last 7 days               | `unknown`    |

Rules:

- Health status must be computed, not stored (no new column)
- Accessible via `$endpoint->healthStatus()` method on the model
- Must use the `WebhookMetrics` service internally
- Thresholds must be configurable via config file
- Add Artisan command: `webhook:health` — outputs a table of all endpoints with their health status

### 1.4 Structured Logging Channel

Add an optional dedicated log channel for webhook activity.

```php
// config/webhook-engine.php
'logging' => [
    'channel' => env('WEBHOOK_LOG_CHANNEL', null), // null = use default
    'log_payload' => false,
    'log_headers' => false,
    'log_level' => 'info',
],
```

Rules:

- If `channel` is `null`, use Laravel's default log channel
- Never log payloads or headers unless explicitly enabled
- Log entries must be structured (use context arrays, not string interpolation)
- Log at appropriate levels: `info` for success, `warning` for retry, `error` for failure
- Must not duplicate information already in `webhook_attempts` table

---

## v1.2.0 — Control

### 2.1 Circuit Breaker

Implement a circuit breaker pattern to auto-disable failing endpoints and stop wasting queue resources.

States:

```
CLOSED → endpoint is healthy, deliveries proceed normally
OPEN → endpoint has failed too many times, deliveries are paused
HALF_OPEN → after cooldown, allow a single test delivery to check recovery
```

Configuration:

```php
'circuit_breaker' => [
    'enabled' => true,
    'failure_threshold' => 10,       // consecutive failures to trip
    'cooldown_seconds' => 300,       // wait before half-open test
    'success_threshold' => 2,        // successes in half-open to close
],
```

Contract:

```php
interface CircuitBreaker
{
    public function isAvailable(WebhookEndpoint $endpoint): bool;
    public function recordSuccess(WebhookEndpoint $endpoint): void;
    public function recordFailure(WebhookEndpoint $endpoint): void;
    public function getState(WebhookEndpoint $endpoint): CircuitState;
    public function reset(WebhookEndpoint $endpoint): void;
}
```

Rules:

- State must be stored in cache (not database) for speed
- Must fire `EndpointCircuitOpened` and `EndpointCircuitClosed` events
- Circuit breaker must be checked before dispatching to queue
- Must be bypassable with `--force` flag in replay command
- Add `webhook:circuit:status` Artisan command
- Add `webhook:circuit:reset {endpoint_id}` Artisan command
- Must be completely disableable via config

### 2.2 Endpoint Tagging

Allow endpoints to be tagged/grouped for organizational purposes.

Schema addition (new migration):

```
webhook_endpoint_tags
├── id
├── endpoint_id (FK)
├── tag (string, indexed)
├── created_at
```

Features:

- `$endpoint->tags()` relationship
- Filter by tag in dashboard
- Filter by tag in `webhook:endpoint:list --tag=payments`
- Dispatch events to all endpoints with a given tag: `Webhook::dispatchToTag('payments', 'order.created', $payload)`
- Tags are simple strings, no separate tags table needed

### 2.3 Endpoint Enable/Disable API

Provide a programmatic way to toggle endpoints.

```php
Webhook::disable($endpointId, ?string $reason = null);
Webhook::enable($endpointId);
Webhook::isEnabled($endpointId): bool;
```

Rules:

- Add `disabled_reason` nullable column to `webhook_endpoints`
- Add `disabled_at` nullable timestamp to `webhook_endpoints`
- When disabled, no new events should be queued for that endpoint
- Must fire `EndpointDisabled` and `EndpointEnabled` events
- Dashboard must show disabled status with reason
- CLI: `webhook:endpoint:disable {id} --reason="Maintenance"` and `webhook:endpoint:enable {id}`

### 2.4 Max Retry Configuration Per Endpoint

Allow per-endpoint override of global retry settings.

Schema addition:

- Add `max_retries` nullable integer to `webhook_endpoints` (null = use global default)
- Add `retry_strategy` nullable string to `webhook_endpoints` (null = use global default)

```php
// Global default from config
'retry' => [
    'max_attempts' => 5,
    'strategy' => 'exponential', // or class reference
],

// Per-endpoint override
$endpoint->max_retries = 10;
$endpoint->retry_strategy = CustomRetryStrategy::class;
```

Rules:

- Per-endpoint config always takes precedence over global
- Strategy class must implement `RetryStrategy` contract
- Must validate that strategy class exists and implements the contract
- Null values must fall back to global config cleanly

---

## v1.3.0 — Developer UX

### 3.1 Webhook Testing Facade

Provide a clean testing API so users can test webhook behavior in their app's test suite without hitting real endpoints.

```php
use Chirag\WebhookEngine\Facades\Webhook;

// Fake all outbound webhooks
Webhook::fake();

// Assert a webhook was dispatched
Webhook::assertDispatched('order.created');

// Assert with payload inspection
Webhook::assertDispatched('order.created', function ($event) {
    return $event->payload['order_id'] === 123;
});

// Assert nothing was dispatched
Webhook::assertNothingDispatched();

// Assert dispatched count
Webhook::assertDispatchedTimes('order.created', 3);

// Get all faked dispatches for inspection
Webhook::dispatched('order.created'); // Collection
```

Rules:

- Must mirror Laravel's `Event::fake()`, `Queue::fake()` patterns
- Faked webhooks must not hit the network or touch the database
- Must be usable in both unit and feature tests
- Provide a `WebhookFake` class that replaces the real service in the container
- Include a trait `InteractsWithWebhooks` for test classes

### 3.2 Webhook Simulator (Inbound)

An Artisan command to simulate inbound webhook deliveries for local development.

```bash
# Simulate a Stripe-style webhook
php artisan webhook:simulate stripe --event=payment_intent.succeeded

# Simulate with custom payload from a JSON file (use route_token, not ID)
php artisan webhook:simulate custom --payload=tests/fixtures/sample.json --endpoint=ep_a8f3kx9m2b7q

# Simulate with inline JSON
php artisan webhook:simulate custom --endpoint=ep_a8f3kx9m2b7q --json='{"event":"test"}'
```

Rules:

- Must generate proper HMAC signature using the endpoint's secret
- Must send a real HTTP request to the registered inbound URL using the endpoint's `route_token`
- The `--endpoint` flag accepts the `route_token` value (not the internal ID)
- Must output request/response details to terminal
- Include a few built-in templates (generic, Stripe-like, GitHub-like) as fixture files in the package
- Templates are for structure only — not real provider payloads

### 3.3 Debug Mode

Add a verbose debug mode for development environments.

```php
'debug' => [
    'enabled' => env('WEBHOOK_DEBUG', false),
    'log_full_payload' => false,
    'log_full_headers' => false,
    'log_full_response_body' => false,
],
```

When debug is enabled:

- Log full request/response cycle to the webhook log channel
- Add timing breakdowns (DNS, connect, TLS, transfer) if available via cURL info
- Dashboard shows a "Debug" tab with recent raw logs
- Never enable in production — add a runtime warning if `APP_ENV=production` and debug is on

### 3.4 Webhook Tinker Helper

Register useful macros/helpers in Tinker for quick debugging.

```php
// In Tinker
>>> Webhook::inspect($eventId)     // Pretty-print event + all attempts
>>> Webhook::lastFailed()          // Get the most recent failed event
>>> Webhook::retryLast()           // Retry the most recent failed event
>>> Webhook::stats()               // Quick metrics summary
```

Rules:

- These are convenience methods on the Facade, not Tinker-specific code
- Must work in any context (Tinker, controllers, commands)
- Output must be human-readable when called from CLI

---

## v1.4.0 — Hardening

### 4.1 Rate Limiting (Outbound)

Prevent overwhelming destination servers by rate-limiting outbound deliveries per endpoint.

```php
'rate_limiting' => [
    'enabled' => false,
    'default_per_minute' => 60,
],
```

Schema addition:

- Add `rate_limit_per_minute` nullable integer to `webhook_endpoints`

Rules:

- Use Laravel's `RateLimiter` under the hood
- Rate limit key: `webhook-engine:{endpoint_id}`
- If limit is hit, delay the job (re-queue with backoff), do not fail it
- Must not count failed attempts toward the rate limit
- Must be per-endpoint configurable
- Dashboard must show current rate limit status

### 4.2 Payload Validation (Outbound)

Allow users to define payload schemas that are validated before dispatch.

```php
// In config or per-endpoint
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

Rules:

- Use Laravel's `Validator` — no external schema libraries
- Validation runs before persisting the event
- Invalid payloads must throw `InvalidWebhookPayloadException`
- Validation must be optional and off by default
- Log validation failures at `warning` level

### 4.3 IP Allowlist (Inbound)

Allow restricting inbound webhooks to known IP addresses.

```php
'inbound' => [
    'ip_allowlist' => [
        'enabled' => false,
        'global' => [], // applies to all inbound endpoints
        // per-endpoint allowlists via database
    ],
],
```

Schema addition:

- Add `allowed_ips` nullable JSON to `webhook_endpoints` (for inbound endpoints only)

Rules:

- Check IP before signature verification (fail fast)
- Support CIDR notation (e.g., `192.168.1.0/24`)
- Support both IPv4 and IPv6
- Return `403 Forbidden` for blocked IPs
- Log blocked attempts at `warning` level
- Must handle `X-Forwarded-For` when behind a proxy (configurable)

### 4.4 Webhook Secret Rotation

Allow rotating endpoint secrets without downtime.

```php
Webhook::rotateSecret($endpointId): string; // returns new secret
```

Schema addition:

- Add `previous_secret` nullable string to `webhook_endpoints`
- Add `secret_rotated_at` nullable timestamp to `webhook_endpoints`

Rules:

- After rotation, accept signatures from both old and new secret for a configurable grace period (default: 24 hours)
- After grace period, old secret is permanently deleted
- Must fire `EndpointSecretRotated` event
- CLI: `webhook:secret:rotate {endpoint_id}`
- Dashboard must show rotation status and grace period countdown
- Add a scheduled job to clean up expired previous secrets

### 4.5 Idempotency Keys (Outbound)

Add idempotency key support to prevent duplicate deliveries.

Schema addition:

- Add `idempotency_key` unique nullable string to `webhook_events`

```php
Webhook::dispatch('order.created', $payload, [
    'idempotency_key' => 'order-123-created',
]);
```

Rules:

- If a duplicate key is dispatched, skip silently (log at `debug` level)
- Idempotency keys must respect retention — pruned with the event
- Must be optional — if no key provided, no dedup check
- Index the `idempotency_key` column

---

## v1.5.0 — Dashboard v2

### 5.1 Dashboard Stats Overview

Add a summary stats panel at the top of the dashboard.

Must show:

- Total events today (sent / failed / pending)
- Success rate (24h rolling)
- Average response time (24h rolling)
- Endpoints status breakdown (healthy / degraded / failing)
- Active circuit breakers (if any)

Rules:

- Use the `WebhookMetrics` service from v1.1
- Cache dashboard stats (don't hit DB on every page load)
- Stats must be Blade-rendered, no JavaScript charting libraries
- Use simple HTML/CSS bar charts or progress indicators if needed

### 5.2 Event Timeline View

Add a detail page for individual events showing a visual timeline of all attempts.

Must show:

- Event metadata (name, endpoint, payload preview, created_at)
- Each attempt in chronological order with:
  - Timestamp
  - HTTP status code (color-coded: green/yellow/red)
  - Duration (ms)
  - Error message if failed
  - Expandable response body (if stored)
- Current status badge
- Retry and Replay action buttons

Rules:

- Payload must be pretty-printed JSON with syntax highlighting (use simple CSS, no JS library)
- Secrets and sensitive headers must be masked
- Response body expandable only if `log_response_body` is enabled
- Replay button must require confirmation

### 5.3 Bulk Actions

Add bulk operations on the events list page.

Actions:

- Bulk replay (select multiple failed events, replay all)
- Bulk delete (select multiple events, prune them)
- Bulk retry (re-queue selected events for retry)

Rules:

- Bulk actions must be queued (don't process 100 replays synchronously)
- Maximum batch size: 100 per action (configurable)
- Show confirmation modal before executing
- Must require the same authorization gate as the dashboard
- Add `webhook:replay --status=failed --endpoint={route_token}` CLI for bulk replay from command line

### 5.4 Endpoint Detail Page

A dedicated page per endpoint showing:

- Endpoint config (name, URL, direction, active status, timeout, tags)
- Health status with history
- Recent events list (paginated)
- Success/failure rate sparkline (last 7 days, CSS-only)
- Circuit breaker state (if applicable)
- Quick actions: disable/enable, rotate secret, reset circuit breaker

### 5.5 Dark Mode

Add a dark mode toggle for the dashboard.

Rules:

- Use CSS variables for theming
- Respect `prefers-color-scheme` by default
- Allow manual toggle that persists via cookie
- Must not add any JavaScript framework — use minimal vanilla JS for toggle
- Default theme configurable via config file

---

## New CLI Commands Summary

| Command                                   | Version | Description                              |
| ----------------------------------------- | ------- | ---------------------------------------- |
| `webhook:health`                          | v1.1.0  | Show health status of all endpoints      |
| `webhook:circuit:status`                  | v1.2.0  | Show circuit breaker states              |
| `webhook:circuit:reset {endpoint_id}`     | v1.2.0  | Reset circuit breaker for an endpoint    |
| `webhook:endpoint:disable {id}`           | v1.2.0  | Disable an endpoint                      |
| `webhook:endpoint:enable {id}`            | v1.2.0  | Enable an endpoint                       |
| `webhook:simulate {type}`                 | v1.3.0  | Simulate inbound webhook delivery        |
| `webhook:secret:rotate {endpoint_id}`     | v1.4.0  | Rotate endpoint secret                   |
| `webhook:replay --status= --endpoint=`    | v1.5.0  | Bulk replay with filters                 |

---

## New Migrations Summary

| Migration                                  | Version | Changes                                     |
| ------------------------------------------ | ------- | ------------------------------------------- |
| Add route_token to endpoints               | v1.1.0  | `route_token` unique string column + backfill |
| Add endpoint tags table                    | v1.2.0  | New `webhook_endpoint_tags` table           |
| Add endpoint disable fields               | v1.2.0  | `disabled_reason`, `disabled_at` columns    |
| Add per-endpoint retry config              | v1.2.0  | `max_retries`, `retry_strategy` columns     |
| Add rate limit to endpoints                | v1.4.0  | `rate_limit_per_minute` column              |
| Add allowed IPs to endpoints               | v1.4.0  | `allowed_ips` JSON column                   |
| Add secret rotation fields                 | v1.4.0  | `previous_secret`, `secret_rotated_at`      |
| Add idempotency key to events              | v1.4.0  | `idempotency_key` unique column             |

All migrations must be:

- Nullable or have defaults (backward-compatible)
- Indexed where appropriate
- Published via `php artisan vendor:publish`

---

## New Contracts Summary

| Contract             | Version | Purpose                                     |
| -------------------- | ------- | ------------------------------------------- |
| `WebhookMetrics`     | v1.1.0  | Metrics aggregation interface               |
| `CircuitBreaker`     | v1.2.0  | Circuit breaker state management            |
| `PayloadValidator`   | v1.4.0  | Outbound payload validation interface       |

All contracts go in `Contracts/` namespace. Default implementations in `Services/`.

---

## New Events Summary

| Event                          | Version | Fires When                              |
| ------------------------------ | ------- | --------------------------------------- |
| `WebhookSending`               | v1.1.0  | Before outbound HTTP call               |
| `WebhookSent`                  | v1.1.0  | After successful delivery               |
| `WebhookFailed`                | v1.1.0  | After a failed attempt                  |
| `WebhookRetriesExhausted`      | v1.1.0  | All retries used up                     |
| `WebhookReplayRequested`       | v1.1.0  | Replay triggered                        |
| `InboundWebhookReceived`       | v1.1.0  | Inbound payload arrives                 |
| `InboundWebhookProcessed`      | v1.1.0  | Inbound processing succeeds             |
| `InboundWebhookFailed`         | v1.1.0  | Inbound processing fails                |
| `EndpointDisabled`             | v1.1.0  | Endpoint auto-disabled or manually      |
| `EndpointEnabled`              | v1.2.0  | Endpoint re-enabled                     |
| `EndpointCircuitOpened`        | v1.2.0  | Circuit breaker trips                   |
| `EndpointCircuitClosed`        | v1.2.0  | Circuit breaker recovers                |
| `EndpointSecretRotated`        | v1.4.0  | Secret rotation completed               |

---

## Testing Requirements Per Version

Every version must ship with:

- Unit tests for all new contracts and their default implementations
- Feature tests for all new Artisan commands
- Feature tests for all new dashboard routes/pages
- Integration tests for any new event dispatching
- Tests confirming backward compatibility with v1.0 behavior

Test naming convention: `{Feature}{Behavior}Test.php`

Examples:

```
Tests/Unit/RouteTokenGenerationTest.php
Tests/Unit/CircuitBreakerTest.php
Tests/Unit/WebhookMetricsTest.php
Tests/Feature/InboundRouteTokenTest.php
Tests/Feature/EndpointHealthCommandTest.php
Tests/Feature/BulkReplayTest.php
Tests/Feature/SecretRotationTest.php
Tests/Feature/RateLimitingTest.php
```

---

## Implementation Order

Follow this order strictly. Each version must be fully complete, tested, and tagged before starting the next.

```
v1.1.0 Observability
  ├── 1. Inbound route tokens (migration + backfill + routing change) ← FIRST
  ├── 2. Laravel Events (all 9 events)
  ├── 3. WebhookMetrics contract + implementation
  ├── 4. Endpoint health status computation
  ├── 5. Structured logging channel
  └── 6. Tests + docs for all above

v1.2.0 Control
  ├── 1. Circuit breaker contract + implementation
  ├── 2. Endpoint tagging (migration + model + commands)
  ├── 3. Endpoint enable/disable API (migration + facade + commands)
  ├── 4. Per-endpoint retry config (migration + logic)
  └── 5. Tests + docs for all above

v1.3.0 Developer UX
  ├── 1. Webhook::fake() testing facade
  ├── 2. InteractsWithWebhooks test trait
  ├── 3. Webhook simulator command
  ├── 4. Debug mode configuration
  ├── 5. Tinker/Facade helper methods
  └── 6. Tests + docs for all above

v1.4.0 Hardening
  ├── 1. Rate limiting (migration + service + config)
  ├── 2. Payload validation (contract + implementation)
  ├── 3. IP allowlist (migration + middleware + config)
  ├── 4. Secret rotation (migration + service + command)
  ├── 5. Idempotency keys (migration + dispatch logic)
  └── 6. Tests + docs for all above

v1.5.0 Dashboard v2
  ├── 1. Stats overview panel
  ├── 2. Event timeline detail page
  ├── 3. Bulk actions (replay, delete, retry)
  ├── 4. Endpoint detail page
  ├── 5. Dark mode
  └── 6. Tests + docs for all above
```

---

## Final Rules

- **Do not skip milestones.** Complete internal v1.1 before starting v1.2.
- **Do not add Phase 2 features.** No storage drivers, no partitioning, no Kafka, no multi-database.
- **Do not add frontend frameworks.** Dashboard stays Blade + minimal vanilla JS.
- **Every feature must have tests.** No exceptions.
- **Keep it Laravel-native.** Use Laravel's cache, queue, rate limiter, validator, events — don't reinvent.
- **Do not tag internal milestones.** No `git tag v1.1.0` etc. — these are planning labels only.
- **The first public git tag is `v0.1.0`** — created only after all internal milestones (v1.0 through v1.5) are complete.
- **Before tagging `v0.1.0`:** `CHANGELOG.md` finalized, `composer.json` set to `0.1.0`, README complete, all tests passing.