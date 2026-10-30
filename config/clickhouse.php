<?php

return [
    'url' => env('CLICKHOUSE_URL', 'http://127.0.0.1:8123'),

    'database' => env('CLICKHOUSE_DATABASE', 'sms_gateway'),
    'username' => env('CLICKHOUSE_USERNAME', 'default'),
    'password' => env('CLICKHOUSE_PASSWORD', ''),

    'connect_timeout' => (float) env('CLICKHOUSE_CONNECT_TIMEOUT', 1),
    'timeout' => (float) env('CLICKHOUSE_TIMEOUT', 3),

    'async_insert' => (bool) env('CLICKHOUSE_ASYNC_INSERT', true),

    'retention_days' => env('CLICKHOUSE_RETENTION_DAYS'),

    'tables' => [
        'message_events' => env('CLICKHOUSE_TABLE_MESSAGE_EVENTS', 'sms_message_events'),
    ],
];
