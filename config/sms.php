<?php

return [
    // sms configs
    'prices' => [
        'normal' => (int) env('SMS_NORMAL_PRICE', 100),
        'express' => (int) env('SMS_EXPRESS_PRICE', 100),
    ],
    'reservation_ttl_minutes' => (int) env('SMS_RESERVATION_TTL_MINUTES', 15),

];
