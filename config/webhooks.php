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
    ],

];
