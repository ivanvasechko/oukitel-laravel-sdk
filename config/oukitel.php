<?php

declare(strict_types=1);

$regions = [];
foreach (['EU', 'US', 'CN'] as $region) {
    foreach (['base_url', 'app_id', 'app_version', 'user_domain', 'user_domain_secret'] as $field) {
        $regions[$region][$field] = env('OUKITEL_'.$region.'_'.strtoupper($field));
    }
}

return [
    'regions' => $regions,
    'http' => [
        'timeout_seconds' => env('OUKITEL_TIMEOUT_SECONDS', 10),
        'connect_timeout_seconds' => env('OUKITEL_CONNECT_TIMEOUT_SECONDS', 3),
        'retry_times' => env('OUKITEL_RETRY_TIMES', 3),
        'retry_delay_milliseconds' => env('OUKITEL_RETRY_DELAY_MILLISECONDS', 250),
        'user_agent' => env('OUKITEL_USER_AGENT', 'okhttp/4.9.3'),
    ],
    'session' => [
        'refresh_before_seconds' => env('OUKITEL_REFRESH_BEFORE_SECONDS', 120),
        'login_failure_cooldown_minutes' => env('OUKITEL_LOGIN_FAILURE_COOLDOWN_MINUTES', 5),
        'use_refresh_token' => env('OUKITEL_USE_REFRESH_TOKEN', false),
        'cache_store' => env('OUKITEL_CACHE_STORE'),
        'cache_prefix' => env('OUKITEL_CACHE_PREFIX', 'oukitel'),
    ],
    'logging' => ['channel' => env('OUKITEL_LOG_CHANNEL')],
    'probe' => [
        'login' => env('OUKITEL_PROBE_LOGIN'),
        'password' => env('OUKITEL_PROBE_PASSWORD'),
    ],
];
