<?php

return [
    // sms configs
    'prices' => [
        'normal' => (int) env('SMS_NORMAL_PRICE', 100),
        'express' => (int) env('SMS_EXPRESS_PRICE', 100),
    ],
    'reservation_ttl_minutes' => (int) env('SMS_RESERVATION_TTL_MINUTES', 15),

    /*
    | Retrying transient operator failures (connection errors, 429, 5xx).
    |
    | The worker never sleeps: a failed attempt only records next_retry_at and
    | the sms:republish-retries command puts the message back on the topic
    | once that time arrives. Keep the total backoff below the reservation
    | TTL so funds are settled before the hold is considered overdue.
    */
    'retry' => [
        'max_attempts' => (int) env('SMS_RETRY_MAX_ATTEMPTS', 5),

        // Seconds to wait before the next attempt, indexed by attempts - 1.
        // The last value is reused for any further attempts.
        'backoff_seconds' => [5, 30, 120, 300, 300],

        // How many due messages a single republish run picks up.
        'republish_batch' => (int) env('SMS_RETRY_REPUBLISH_BATCH', 200),
    ],

];
