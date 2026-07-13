<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Installation Type
    |--------------------------------------------------------------------------
    |
    | "single" binds a default SpApiManager from environment credentials.
    | "multi" expects Seller / Credentials models for each connection.
    |
    */

    'installation_type' => env('AMAZON_SPAPI_INSTALLATION_TYPE', 'single'),

    'single' => [
        'lwa' => [
            'client_id' => env('AMAZON_SPAPI_LWA_CLIENT_ID'),
            'client_secret' => env('AMAZON_SPAPI_LWA_CLIENT_SECRET'),
            'refresh_token' => env('AMAZON_SPAPI_LWA_REFRESH_TOKEN'),
        ],

        // NA, EU, or FE
        'endpoint' => env('AMAZON_SPAPI_ENDPOINT_REGION', 'NA'),

        'sandbox' => (bool) env('AMAZON_SPAPI_SANDBOX', false),
    ],

    'oauth' => [
        'application_id' => env('AMAZON_SPAPI_APPLICATION_ID'),
        'redirect_uri' => env('AMAZON_SPAPI_REDIRECT_URI'),
        'lwa_token_endpoint' => env(
            'AMAZON_SPAPI_LWA_TOKEN_ENDPOINT',
            'https://api.amazon.com/auth/o2/token'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Restricted Data Tokens (RDT)
    |--------------------------------------------------------------------------
    |
    | Defaults used when creating RDTs for PII-restricted operations.
    | data_elements applies to Orders paths that accept them (buyerInfo,
    | shippingAddress). target_application is for delegatee apps.
    |
    */

    'rdt' => [
        // Automatically attach RDTs on restricted SP-API calls
        'auto' => (bool) env('AMAZON_SPAPI_RDT_AUTO', true),

        // Used by auto-RDT for Orders paths that accept dataElements
        'data_elements' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AMAZON_SPAPI_RDT_DATA_ELEMENTS', 'buyerInfo,shippingAddress'))
        ))),

        'target_application' => env('AMAZON_SPAPI_RDT_TARGET_APPLICATION'),
        'skip_in_sandbox' => (bool) env('AMAZON_SPAPI_RDT_SKIP_IN_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Retries
    |--------------------------------------------------------------------------
    |
    | SP-API is rate-limited. Transient 429 / 5xx responses are common under
    | load; retries with backoff avoid brittle one-shot failures.
    |
    */

    'retry' => [
        'enabled' => (bool) env('AMAZON_SPAPI_RETRY_ENABLED', true),
        'max_attempts' => (int) env('AMAZON_SPAPI_RETRY_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('AMAZON_SPAPI_RETRY_BASE_DELAY_MS', 500),
        'status_codes' => [429, 500, 502, 503, 504],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue workers (reports / feeds)
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('AMAZON_SPAPI_QUEUE_CONNECTION'),
        'queue' => env('AMAZON_SPAPI_QUEUE', 'amazon-spapi'),
        'report_poll_seconds' => (int) env('AMAZON_SPAPI_REPORT_POLL_SECONDS', 60),
        'feed_poll_seconds' => (int) env('AMAZON_SPAPI_FEED_POLL_SECONDS', 60),
        'max_poll_attempts' => (int) env('AMAZON_SPAPI_MAX_POLL_ATTEMPTS', 60),
    ],

    'debug' => (bool) env('AMAZON_SPAPI_DEBUG', false),
    'debug_file' => env('AMAZON_SPAPI_DEBUG_FILE'),
];
