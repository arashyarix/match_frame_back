<?php

return [
    // The API + Sanctum endpoints the browser may call cross-origin.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed origins
    |--------------------------------------------------------------------------
    | Auth uses Sanctum BEARER TOKENS (not cookies), so credentials are off and
    | it's safe to allow any origin in development. This avoids the very common
    | "Failed to fetch" CORS error when the frontend runs on an unexpected port
    | (e.g. Next.js falling back to :3001).
    |
    | In production, lock this down by setting FRONTEND_URLS, e.g.
    |   FRONTEND_URLS=https://app.matchframe.app
    */
    'allowed_origins' => array_values(array_filter(
        explode(',', env('FRONTEND_URLS', '*'))
    )),

    // Also always allow localhost / 127.0.0.1 on any port for local dev.
    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer tokens, not cookies → credentials not required (keeps '*' valid).
    'supports_credentials' => false,
];
