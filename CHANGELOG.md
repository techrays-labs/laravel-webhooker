# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-02-21

### Added

#### Core (v1.0)

- Outbound webhook dispatch with queue-based async delivery
- Exponential backoff retry strategy (configurable max attempts, delay, multiplier)
- HMAC signature generation and verification (SHA-256)
- Inbound webhook receiver with signature verification middleware
- Inbound event deduplication via `X-Webhook-Event-ID` header
- Async inbound event processing via queue jobs
- Webhook endpoint registration (inbound and outbound)
- Delivery attempt logging with response status, duration, and error tracking
- Blade-based dashboard with event filtering, attempt inspection, and pagination
- Dashboard authorization gate (`viewWebhookDashboard`)
- Artisan commands: `webhook:prune`, `webhook:replay`, `webhook:endpoint:list`
- Configurable retention period with `webhook:prune` command (default: 30 days)
- Configuration file with options for retry, timeout, signing, queue, dashboard
- Database migrations for `webhook_endpoints`, `webhook_events`, `webhook_attempts`
- Proper indexes on `status`, `endpoint_id`, `created_at`, `next_retry_at`
- Laravel 10, 11, and 12 support

#### Observability (v1.1)

- Alphanumeric route tokens (`ep_xxxxxxxxxxxx`) for inbound webhook URLs replacing sequential integer IDs
- Auto-generation of route tokens on endpoint creation with backfill migration for existing data
- Route model binding via `route_token` for all inbound endpoints
- Laravel event dispatching at key lifecycle points: `WebhookSending`, `WebhookSent`, `WebhookFailed`, `WebhookRetriesExhausted`, `WebhookReplayRequested`, `InboundWebhookReceived`, `InboundWebhookProcessed`, `InboundWebhookFailed`, `EndpointDisabled`
- `WebhookMetrics` contract and `EloquentWebhookMetrics` implementation with cacheable aggregated stats
- `MetricsSummary` and `EndpointHealth` DTOs
- Computed endpoint health status (`healthy`, `degraded`, `failing`, `unknown`) with configurable thresholds
- `webhook:health` Artisan command showing health status of all endpoints
- Structured logging via `WebhookLogger` with optional dedicated log channel
- Configurable payload and header logging

#### Control (v1.2)

- Circuit breaker pattern (`CircuitBreaker` contract and `CacheCircuitBreaker` implementation)
- Circuit breaker states: `CLOSED`, `OPEN`, `HALF_OPEN` with configurable thresholds and cooldown
- `EndpointCircuitOpened` and `EndpointCircuitClosed` events
- `webhook:circuit:status` and `webhook:circuit:reset` Artisan commands
- Endpoint tagging system with `webhook_endpoint_tags` table
- `attachTag()`, `detachTag()`, `hasTag()` methods on `WebhookEndpoint` model
- `dispatchToTag()` method for dispatching to all endpoints with a given tag
- Tag filtering in `webhook:endpoint:list --tag=` and dashboard events page
- Endpoint enable/disable API: `Webhook::disable()`, `Webhook::enable()`, `Webhook::isEnabled()`
- `disabled_reason` and `disabled_at` columns with `EndpointDisabled` and `EndpointEnabled` events
- `webhook:endpoint:disable` and `webhook:endpoint:enable` Artisan commands
- Per-endpoint retry configuration via `max_retries` and `retry_strategy` columns

#### Developer UX (v1.3)

- `Webhook::fake()` testing facade with `assertDispatched()`, `assertNothingDispatched()`, `assertDispatchedTimes()`, `dispatched()` methods
- `WebhookFake` class replacing real service in test container
- `InteractsWithWebhooks` test trait with `fakeWebhooks()` method
- `webhook:simulate` Artisan command for simulating inbound webhook deliveries
- Built-in templates for generic, Stripe-like, and GitHub-like webhook payloads
- Debug mode configuration with full payload, header, and response body logging
- Runtime warning when debug mode is enabled in production
- Tinker/facade helpers: `Webhook::inspect()`, `Webhook::lastFailed()`, `Webhook::retryLast()`, `Webhook::stats()`

#### Hardening (v1.4)

- Outbound rate limiting per endpoint using Laravel's `RateLimiter` (configurable, off by default)
- `rate_limit_per_minute` column on `webhook_endpoints`
- Payload validation with `PayloadValidator` contract and `ConfigPayloadValidator` using Laravel's Validator
- `InvalidWebhookPayloadException` for invalid payloads
- IP allowlist middleware for inbound webhooks with CIDR notation and IPv4 support
- `allowed_ips` JSON column on `webhook_endpoints` for per-endpoint allowlists
- `X-Forwarded-For` support for proxy environments
- Secret rotation: `Webhook::rotateSecret()` and `webhook:secret:rotate` Artisan command
- Grace period for accepting previous secret after rotation (configurable, default: 24 hours)
- `webhook:secret:cleanup` Artisan command for removing expired previous secrets
- `EndpointSecretRotated` event
- Idempotency keys for outbound webhooks via `idempotency_key` on `webhook_events`

#### Dashboard v2 (v1.5)

- Stats overview panel: total events, delivered, failed, pending, success rate, avg response time, endpoint health, open circuits
- Event timeline detail page with chronological attempt visualization, color-coded status badges, expandable response bodies
- Replay button on event detail page with confirmation
- Bulk actions: select multiple events for replay or delete with max batch size
- `webhook:replay --status= --endpoint=` CLI for bulk replay with filters
- Endpoint detail page with configuration, health status, circuit breaker state, secret rotation status
- 7-day success/failure rate sparkline (CSS-only) on endpoint detail page
- Dark mode with CSS variables, `prefers-color-scheme` support, manual toggle with cookie persistence

### Database Migrations

- `create_webhook_endpoints_table` - Core endpoints table
- `create_webhook_events_table` - Core events table with indexes
- `create_webhook_attempts_table` - Delivery attempt records
- `add_route_token_to_webhook_endpoints_table` - Alphanumeric route tokens
- `create_webhook_endpoint_tags_table` - Endpoint tagging
- `add_disable_columns_to_webhook_endpoints_table` - Enable/disable fields
- `add_retry_columns_to_webhook_endpoints_table` - Per-endpoint retry config
- `add_rate_limit_to_webhook_endpoints_table` - Rate limiting
- `add_allowed_ips_to_webhook_endpoints_table` - IP allowlists
- `add_secret_rotation_to_webhook_endpoints_table` - Secret rotation fields
- `add_idempotency_key_to_webhook_events_table` - Idempotency keys

### Contracts

- `WebhookRepository` - All data persistence operations
- `RetryStrategy` - Retry timing calculation
- `SignatureGenerator` - HMAC signature generation and verification
- `InboundProcessor` - Inbound webhook processing
- `WebhookMetrics` - Metrics aggregation
- `CircuitBreaker` - Circuit breaker state management
- `PayloadValidator` - Outbound payload validation

### Test Suite

- Comprehensive test suite with 181 tests and 388 assertions
