<?php

return [
    'brokers' => env('KAFKA_BROKERS', '127.0.0.1:9092'),

    'client_id' => env('KAFKA_CLIENT_ID', 'sms-gateway'),

    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP', 'sms-gateway'),

    'topics' => [
        'normal' => env('KAFKA_TOPIC_NORMAL', 'normal'),
        'express' => env('KAFKA_TOPIC_EXPRESS', 'express'),
        'express_dlq' => env('KAFKA_TOPIC_EXPRESS_DLQ', 'express-dlq'),
    ],

    'connect_timeout' => (float) env('KAFKA_CONNECT_TIMEOUT', 3),
    'send_timeout' => (float) env('KAFKA_SEND_TIMEOUT', 5),
    'recv_timeout' => (float) env('KAFKA_RECV_TIMEOUT', 5),

    'auto_commit' => env('KAFKA_AUTO_COMMIT', true),

    'flush_retries' => 2,

    'compression' => env('KAFKA_COMPRESSION_TYPE', 'none'),

    'message_id_key' => env('KAFKA_MESSAGE_ID_KEY', 'message-id'),
];
