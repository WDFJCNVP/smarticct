<?php

return [

    // Redirect http -> https (turn on in production only, after trusted proxies are set).
    'force_https' => env('SECURITY_FORCE_HTTPS', false),

    // Strict-Transport-Security. Start small, raise later (see guide).
    'hsts' => [
        'enabled'            => env('SECURITY_HSTS', false),
        'max_age'            => env('SECURITY_HSTS_MAX_AGE', 300),
        'include_subdomains' => env('SECURITY_HSTS_SUBDOMAINS', false),
    ],

    // Cache-Control: no-store on dynamic pages (balances, PIN, card data).
    'no_store' => env('SECURITY_NO_STORE', true),

    'csp' => [
        // off | report-only | enforce
        'mode'        => env('SECURITY_CSP_MODE', 'report-only'),

        // Alpine needs this until livewire.csp_safe = true works for every component.
        'unsafe_eval' => env('SECURITY_CSP_UNSAFE_EVAL', true),

        // Hosts for fonts/styles (the Livewire starter kit loads Instrument Sans from Bunny Fonts).
        'style_hosts' => ['https://fonts.bunny.net'],

        'img'         => ["'self'", 'data:', 'blob:'],

        // Extra connect-src entries, comma separated in .env (usually none).
        'connect'     => array_filter(explode(',', (string) env('SECURITY_CSP_CONNECT', ''))),
    ],

    // Used only to allow the WebSocket connection in connect-src.
    'reverb' => [
        'host'   => env('REVERB_HOST'),
        'port'   => env('REVERB_PORT'),
        'scheme' => env('REVERB_SCHEME', 'https'),
    ],
];