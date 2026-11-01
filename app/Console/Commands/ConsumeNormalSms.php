<?php

namespace App\Console\Commands;

use App\Enums\SmsType;

class ConsumeNormalSms extends ConsumeSmsTopic
{
    protected $signature = 'kafka:consume-normal';

    protected $description = 'Consume Normal SMS messages from Kafka';

    protected function type(): SmsType
    {
        return SmsType::Normal;
    }
}
