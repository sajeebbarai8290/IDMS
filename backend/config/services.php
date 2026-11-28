<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'keycloak' => [
        'base_url' => env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),
        'realm' => env('KEYCLOAK_REALM', 'idms-gov-realm'),
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'idms-backend-api'),
        'public_key' => env('KEYCLOAK_PUBLIC_KEY'),
        'jwks_url' => env('KEYCLOAK_JWKS_URL', 'http://localhost:8080/realms/idms-gov-realm/protocol/openid-connect/certs'),
        'issuer' => env('KEYCLOAK_TOKEN_ISSUER', 'http://localhost:8080/realms/idms-gov-realm'),
        'audience' => env('KEYCLOAK_TOKEN_AUDIENCE', 'idms-backend-api'),
        'cache_ttl' => env('KEYCLOAK_JWKS_CACHE_TTL', 86400),
        'admin_driver' => env('KEYCLOAK_ADMIN_DRIVER', 'mock'), // 'mock' (dev/test) | 'keycloak' (Admin REST API)
        'admin_client_id' => env('KEYCLOAK_ADMIN_CLIENT_ID'),
        'admin_client_secret' => env('KEYCLOAK_ADMIN_CLIENT_SECRET'),
        'admin_timeout_seconds' => (int) env('KEYCLOAK_ADMIN_TIMEOUT_SECONDS', 5),
    ],

    'nid' => [
        'driver' => env('NID_VERIFICATION_DRIVER', 'mock'), // 'mock' (dev/test only) | 'government'
        'base_url' => env('NID_API_BASE_URL'),
        'verify_path' => env('NID_API_VERIFY_PATH'),
        'api_key' => env('NID_API_KEY'),
        'ssl_cert_path' => env('NID_API_SSL_CERT_PATH'),
        'ssl_key_path' => env('NID_API_SSL_KEY_PATH'),
        'timeout_seconds' => (int) env('NID_API_TIMEOUT_SECONDS', 5),
        'max_retries' => (int) env('NID_API_MAX_RETRIES', 2),
        'retry_delay_ms' => (int) env('NID_API_RETRY_DELAY_MS', 250),
        'rate_limit_per_minute' => (int) env('NID_RATE_LIMIT_PER_MINUTE', 30),
        'async_by_default' => (bool) env('NID_VERIFICATION_ASYNC', false),
    ],

    'sms' => [
        'driver' => env('SMS_GATEWAY_DRIVER', 'mock'), // 'mock' (dev/test) | 'teletalk'
        'base_url' => env('SMS_API_BASE_URL'),
        'api_key' => env('SMS_API_KEY'),
        'username' => env('SMS_API_USERNAME'),
        'sender_id' => env('SMS_SENDER_ID', 'MoDMR-IDMS'),
        'timeout_seconds' => (int) env('SMS_API_TIMEOUT_SECONDS', 5),
    ],

    'push' => [
        'driver' => env('PUSH_NOTIFICATION_DRIVER', 'mock'), // 'mock' (dev/test) | 'fcm'
        'fcm_endpoint' => env('FCM_API_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
        'fcm_server_key' => env('FCM_SERVER_KEY'),
        'timeout_seconds' => (int) env('PUSH_API_TIMEOUT_SECONDS', 5),
    ],

];
