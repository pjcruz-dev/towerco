<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | TowerOS merges tenant UI origins at runtime (see CorsAllowedOriginResolver).
    | Do not set allowed_origins to "*" in production — use FRONTEND_APP_URL,
    | TOWEROS_TENANT_APP_URL, and optional TOWEROS_CORS_ALLOWED_ORIGINS instead.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-Request-Id',
        'X-Correlation-Id',
        'X-Tenant-Id',
        'X-Tenant-Domain',
        'X-Session-Id',
    ],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 600,

    'supports_credentials' => false,

];
