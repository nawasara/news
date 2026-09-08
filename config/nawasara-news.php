<?php

return [
    // Base URL of the WordPress website to fetch news from.
    'wp_base_url' => env('NAWASARA_NEWS_WP_BASE_URL', 'https://ponorogo.go.id'),

    'wp_http_timeout' => env('NAWASARA_NEWS_WP_HTTP_TIMEOUT', 15),

    'scheduler' => [
        'enabled' => env('NAWASARA_NEWS_SCHEDULER_ENABLED', true),
    ],

    // Minutes between scheduled syncs.
    'sync_interval' => env('NAWASARA_NEWS_SYNC_INTERVAL', 60),

    // Fallback if the nawasara_news_settings row is somehow missing.
    'default_latest_post_limit' => 50,

    // Human readable timezone for displaying the post date in the frontend.
    'display_timezone' => env('NAWASARA_NEWS_DISPLAY_TIMEZONE', 'Asia/Jakarta'),
];
