<?php

namespace App\Contracts;

use App\Exceptions\SmsQueueUnavailableException;
use App\Models\SmsMessage;

interface SmsQueue
{
    public function publish(SmsMessage $message): void;
}
