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
    ],

];
