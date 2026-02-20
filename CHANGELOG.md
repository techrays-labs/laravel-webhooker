# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-02-20

### Added

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
- Comprehensive test suite (58 tests, 140 assertions)
- Laravel 10, 11, and 12 support
