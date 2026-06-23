<?php

return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'admin'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'admins',
        ],

        // The Filament panel authenticates against this guard.
        'admin' => [
            'driver'   => 'session',
            'provider' => 'admins',
        ],

        // API token auth for the Next.js frontend (Sanctum bearer tokens).
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        // Panel admins (Filament login).
        'admins' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Admin::class,
        ],

        // App end users (API auth via Sanctum tokens).
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\AuthUser::class,
        ],
    ],

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
