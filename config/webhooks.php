<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how failed webhook deliveries are retried. The default strategy
    | uses exponential backoff. You may override the retry strategy by binding
    | your own implementation of the RetryStrategy contract.
    |
    */

    'retry' => [
        'max_attempts' => 5,
        'base_delay_seconds' => 10,
        'multiplier' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Default timeout in seconds for outbound webhook HTTP requests.
    | This can be overridden per endpoint via the timeout_seconds column.
    |
    */

    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Signing Algorithm
    |--------------------------------------------------------------------------
    |
    | The HMAC algorithm used for generating and verifying webhook signatures.
    |
    */

    'signing_algorithm' => 'sha256',

    /*
    |--------------------------------------------------------------------------
    | Signature Header
    |--------------------------------------------------------------------------
    |
    | The HTTP header name used to transmit the webhook signature.
    |
    */

    'signature_header' => 'X-Webhook-Signature',

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | The queue connection and queue name used for dispatching webhook jobs.
    | Set to null to use the application defaults.
    |
    */

    'queue' => [
        'connection' => null,
        'name' => 'webhooks',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention & Pruning
    |--------------------------------------------------------------------------
    |
    | Number of days to retain webhook event records. The webhook:prune
    | command will delete events older than this threshold.
    |
    */

    'retention_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Response Body Storage
    |--------------------------------------------------------------------------
    |
    | Set to false to skip storing response bodies from webhook attempts.
    | This can reduce storage usage for high-volume applications.
    |
    */

    'store_response_body' => true,

    /*
    |--------------------------------------------------------------------------
    | Request Headers Logging
    |--------------------------------------------------------------------------
    |
    | Set to true to store full request headers in webhook attempt records.
    | Disabled by default to avoid leaking sensitive header data.
    |
    */

    'log_request_headers' => false,

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the built-in Blade dashboard. You can disable it entirely,
    | customize the route prefix, middleware, and authorization gate.
    |
    */

    'dashboard' => [
        'enabled' => true,
        'prefix' => 'webhooks',
        'middleware' => ['web', 'auth'],
        'gate' => 'viewWebhookDashboard',
        'max_bulk_size' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Controls caching and thresholds for the webhook metrics service.
    |
    */

    'metrics' => [
        'cache_ttl' => 60,
        'healthy_threshold' => 95,
        'degraded_threshold' => 70,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the circuit breaker pattern for failing endpoints. When an
    | endpoint fails too many times consecutively, the circuit opens and
    | pauses deliveries until a cooldown period has passed.
    |
    */

    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 10,
        'cooldown_seconds' => 300,
        'success_threshold' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Optional dedicated log channel for webhook activity.
    | Set channel to null to use Laravel's default log channel.
    |
    */

    'logging' => [
        'channel' => env('WEBHOOK_LOG_CHANNEL', null),
        'log_payload' => false,
        'log_headers' => false,
        'log_level' => 'info',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | Enable verbose debug mode for development environments.
    | WARNING: Do not enable in production.
    |
    */

    'debug' => [
        'enabled' => env('WEBHOOK_DEBUG', false),
        'log_full_payload' => false,
        'log_full_headers' => false,
        'log_full_response_body' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Rate limit outbound webhook deliveries per endpoint to prevent
    | overwhelming destination servers. Uses Laravel's RateLimiter.
    |
    */

    'rate_limiting' => [
        'enabled' => false,
        'default_per_minute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload Validation Configuration
    |--------------------------------------------------------------------------
    |
    | Define validation schemas for outbound webhook payloads. Uses Laravel's
    | Validator. Invalid payloads throw InvalidWebhookPayloadException.
    |
    */

    'payload_validation' => [
        'enabled' => false,
        'schemas' => [
            // 'order.created' => [
            //     'order_id' => 'required|integer',
            //     'amount' => 'required|numeric',
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound Configuration
    |--------------------------------------------------------------------------
    |
    | Controls inbound webhook processing, including IP allowlisting.
    |
    */

    'inbound' => [
        'ip_allowlist' => [
            'enabled' => false,
            'global' => [],
            'trust_proxy' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Secret Rotation Configuration
    |--------------------------------------------------------------------------
    |
    | Grace period in hours for accepting the previous secret after rotation.
    |
    */

    'secret_rotation' => [
        'grace_period_hours' => 24,
    ],

];
