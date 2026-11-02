<?php

/*
| SmartBazars CORS
| Browsers may call the API only from the SmartBazars website (and localhost while developing).
| Mobile apps are not affected by CORS. Extra origins: CORS_EXTRA_ORIGINS="https://x.com,https://y.com"
*/
$origins = array_filter(array_map('trim', explode(',', (string)env('CORS_EXTRA_ORIGINS', ''))));
$site = rtrim((string)env('FRONTEND_URL', 'https://smartbazars.in'), '/');
$www = preg_replace('#^https://(?!www\.)#', 'https://www.', $site);

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_merge([$site, $www], $origins))),
    // local development (any port on localhost / 127.0.0.1 / private LAN IPs) when APP_ENV=local
    'allowed_origins_patterns' => env('APP_ENV', 'production') === 'production' ? [] : [
        '#^https?://(localhost|127\.0\.0\.1|10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+)(:\d+)?$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
