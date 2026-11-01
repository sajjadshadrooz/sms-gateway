<?php

namespace App\Console\Commands;

use App\Enums\SmsType;

class ConsumeExpressSms extends ConsumeSmsTopic
{
    protected $signature = 'kafka:consume-express';

    protected $description = 'Consume Express SMS messages from Kafka';

    protected function type(): SmsType
    {
        return SmsType::Express;
    }
}
