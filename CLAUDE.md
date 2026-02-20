# Laravel Webhook Engine – Project Plan (v1.0)

## Project Vision

Build a fully open-source, Laravel-native Webhook Reliability Engine that provides:

- Reliable outbound webhook delivery
- Structured inbound webhook handling
- Persistent logging
- Intelligent retry
- Replay capability
- Built-in dashboard
- Retention & pruning
- Extensible architecture for future scaling (Phase 2 ready)

This package must be:

- Composer installable
- Framework-native (Laravel 10+)
- Production-safe
- Zero external service dependency
- Always free & MIT licensed
- Cleanly structured
- Long-term maintainable

---

## 1. Versioning Strategy

We follow **Semantic Versioning (SemVer)**.

- `0.x.x` → Development phase
- `1.0.0` → First stable production release
- `1.x.x` → Backward-compatible improvements
- `2.0.0` → Major architecture/scaling change (Phase 2)

Tag releases properly. Each release must update:

- `CHANGELOG.md`
- Version in `composer.json`

---

## 2. Development Rules (Strict)

### DO

- Follow SOLID principles
- Use dependency inversion (contracts for core services)
- Write unit + feature tests
- Keep public API stable
- Use Laravel conventions
- Use typed properties and strict typing
- Use queue system for all deliveries
- Use configuration file for all behavior
- Keep dashboard minimal (Blade-based)
- Write PHPDoc for public APIs
- Add migration indexes
- Write meaningful commit messages
- Keep code readable over clever

### DO NOT

- Do not hardcode retry logic
- Do not tightly couple storage implementation
- Do not build Kafka/streaming support
- Do not introduce microservice complexity
- Do not add unnecessary frontend frameworks
- Do not store unlimited logs without pruning
- Do not introduce SaaS billing logic
- Do not overengineer Phase 1
- Do not break backward compatibility after v1.0

---

## 3. Core Feature Scope (v1.0)

### Outbound

- Register endpoints
- Dispatch webhook events
- Persist event before sending
- Queue-based delivery
- Exponential retry
- Attempt logging
- Signature generation (HMAC)
- Delivery status tracking
- Replay failed events
- Configurable timeout

### Inbound

- Register inbound endpoints
- Verify signature
- Persist inbound payload
- Deduplicate via event ID
- Async processing job
- Processing status tracking
- Replay inbound processing

### Operational

- Built-in dashboard
- Event filtering
- Attempt inspection
- Pruning job
- Configurable retention days
- Disable dashboard in production

---

## 4. Architecture Requirements

### Core Interfaces (Must Exist)

```
Contracts/
    WebhookRepository.php
    RetryStrategy.php
    SignatureGenerator.php
    InboundProcessor.php
```

All services must depend on interfaces. Default implementations go in:

```
Services/
Strategies/
Support/
```

### Retry Strategy

Interface:

```php
interface RetryStrategy
{
    public function nextRetry(int $attempt): Carbon;
}
```

- Default: exponential backoff
- Must allow override via config

### Storage Strategy

- All database interaction must go through a repository layer
- This ensures Phase 2 scalability later

---

## 5. Database Schema

### `webhook_endpoints`

| Column            | Type                    |
| ----------------- | ----------------------- |
| `id`              | Primary key             |
| `name`            | string                  |
| `url`             | string                  |
| `direction`       | enum: inbound, outbound |
| `secret`          | string                  |
| `is_active`       | boolean                 |
| `timeout_seconds` | integer                 |
| `created_at`      | timestamp               |
| `updated_at`      | timestamp               |

### `webhook_events`

| Column            | Type          |
| ----------------- | ------------- |
| `id`              | Primary key   |
| `endpoint_id`     | Foreign key   |
| `event_name`      | string        |
| `payload`         | json          |
| `status`          | string        |
| `attempts_count`  | integer       |
| `last_attempt_at` | timestamp     |
| `next_retry_at`   | timestamp     |
| `created_at`      | timestamp     |
| `updated_at`      | timestamp     |

### `webhook_attempts`

| Column             | Type              |
| ------------------ | ----------------- |
| `id`               | Primary key       |
| `event_id`         | Foreign key       |
| `request_headers`  | json              |
| `response_status`  | integer           |
| `response_body`    | text (nullable)   |
| `error_message`    | text (nullable)   |
| `duration_ms`      | integer           |
| `attempted_at`     | timestamp         |

### Required Indexes

- `status`
- `endpoint_id`
- `created_at`
- `next_retry_at`

---

## 6. Dashboard Rules

- Blade-based only
- No heavy JS frameworks
- Use pagination
- Must be disableable via config
- Must use authorization gate
- Must not expose secrets in UI
- Must mask sensitive data

---

## 7. Logging & Retention

- Default retention: **30 days**
- Configurable
- Artisan command: `webhook:prune`
- Must not allow infinite growth by default
- Optional: disable response body storage

---

## 8. CLI Commands

Required:

- `webhook:prune`
- `webhook:replay {event_id}`
- `webhook:endpoint:list`

---

## 9. Testing Requirements

Minimum **90% coverage**. Must include:

- Outbound dispatch test
- Retry test
- Signature test
- Inbound verification test
- Replay test
- Pruning test
- Dashboard route protection test

CI must fail if tests fail.

---

## 10. Commit Strategy

### Branching Model

- `main` → stable
- `develop` → active development
- `feature/{name}` → feature branches
- `fix/{name}` → fix branches

### Commit Message Format

Use **conventional commits**:

```
feat: add retry strategy contract
fix: correct signature verification bug
refactor: extract repository layer
docs: update README installation steps
test: add inbound replay test
```

**Never** use vague commits like: `"update"`, `"changes"`, `"fix stuff"`

---

## 11. Pull Request Rules

Each PR must:

- Be small and focused
- Include tests
- Update docs if needed
- Pass CI
- Not introduce breaking changes

---

## 12. README Requirements

README must include:

- What problem this solves
- Feature overview
- Installation steps
- Basic outbound example
- Basic inbound example
- Dashboard usage
- Retention config
- Retry customization
- Contributing guide
- Roadmap
- License

Tone: **production-grade, serious.**

---

## 13. Phase 2 Preparation (Do NOT Implement Yet)

Design must **allow** future:

- Storage driver abstraction
- Table partitioning
- Dead-letter queue
- Per-endpoint retry strategies
- Endpoint health scoring
- Horizontal scaling
- Event batching
- Multi-database support

**Only prepare architecture. Do not implement.**

---

## 14. Performance Constraints

v1.0 must comfortably handle:

- **50k+ webhook events/day**
- With pruning enabled
- With indexed queries
- Without blocking main request thread

All outbound delivery must be **async**.

---

## 15. Security Rules

- Always verify inbound signatures
- Reject invalid signatures
- Mask secrets in logs
- Do not expose sensitive payload fields in dashboard
- Do not log full headers unless enabled

---

## 16. Release Checklist (Before v1.0)

- [ ] All tests pass
- [ ] CI passing
- [ ] Code formatted (Laravel Pint)
- [ ] PHPStan clean
- [ ] README finalized
- [ ] CHANGELOG updated
- [ ] Version bumped
- [ ] Tag created
- [ ] Example demo project tested

---

## 17. Definition of Done (v1.0)

The package is ready when:

- It installs cleanly
- Outbound webhooks retry reliably
- Inbound webhooks verify correctly
- Replay works
- Dashboard works
- Logs prune correctly
- Tests cover major logic
- No critical security risks
- Public API stable

---

## Final Instruction

Implement this package as:

- **Clean**
- **Maintainable**
- **Extensible**
- **Laravel-native**
- **Production-safe**
- **Scale-aware but not overengineered**

Focus on reliability and operational visibility. Do not chase complexity. Build something Laravel developers can trust in production.
