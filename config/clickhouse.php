<?php

return [
    'url' => env('CLICKHOUSE_URL', 'http://127.0.0.1:8123'),

    'database' => env('CLICKHOUSE_DATABASE', 'sms_gateway'),
    'username' => env('CLICKHOUSE_USERNAME', 'default'),
    'password' => env('CLICKHOUSE_PASSWORD', ''),

    'connect_timeout' => (float) env('CLICKHOUSE_CONNECT_TIMEOUT', 1),
    'timeout' => (float) env('CLICKHOUSE_TIMEOUT', 3),

    // The event writer already inserts in batches, so server side buffering is off by default.
    'async_insert' => (bool) env('CLICKHOUSE_ASYNC_INSERT', false),

    'retention_days' => env('CLICKHOUSE_RETENTION_DAYS'),

    // clickhouse:write-events inserts a batch when it reaches batch_size rows
    // or when its oldest row has waited flush_interval_ms, whichever comes first.
    'writer' => [
        'batch_size' => (int) env('CLICKHOUSE_WRITER_BATCH_SIZE', 1000),
        'flush_interval_ms' => (int) env('CLICKHOUSE_WRITER_FLUSH_INTERVAL_MS', 2000),
        'insert_attempts' => (int) env('CLICKHOUSE_WRITER_INSERT_ATTEMPTS', 3),
        'retry_backoff_ms' => (int) env('CLICKHOUSE_WRITER_RETRY_BACKOFF_MS', 1000),
    ],

    'tables' => [
        'message_events' => env('CLICKHOUSE_TABLE_MESSAGE_EVENTS', 'sms_message_events'),
    ],
];
