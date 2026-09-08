<?php

return [
    // Base URL of the Ponorogo WordPress site (wp-json lives under this).
    // No default — must be set, sync fails loudly if missing rather than
    // silently hitting a wrong/placeholder host.
    'wp_base_url' => env('NAWASARA_NEWS_WP_BASE_URL'),

    'wp_http_timeout' => env('NAWASARA_NEWS_WP_HTTP_TIMEOUT', 15),

    'scheduler' => [
        'enabled' => env('NAWASARA_NEWS_SCHEDULER_ENABLED', true),
    ],

    // Minutes between scheduled syncs.
    'sync_interval' => env('NAWASARA_NEWS_SYNC_INTERVAL', 15),

    // Fallback if the nawasara_news_settings row is somehow missing.
    'default_latest_post_limit' => 100,

    // Timezone for displaying tanggal_terbit (API responses + admin table).
    // Storage stays UTC (correct/portable) — this only affects formatting
    // at output time. Asia/Jakarta matches the confirmed offset of the
    // Ponorogo WordPress site's own displayed times (WIB, UTC+7).
    'display_timezone' => env('NAWASARA_NEWS_DISPLAY_TIMEZONE', 'Asia/Jakarta'),
];
