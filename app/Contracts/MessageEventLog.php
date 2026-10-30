<?php

namespace App\Contracts;

use App\Enums\MessageEvent;
use App\Models\SmsMessage;

/**
 * Append-only history of what happened to each SMS message.
 *
 * Implementations must never throw: the log is observability, not the source
 * of truth, so a storage outage is reported and the sending pipeline goes on.
 */
interface MessageEventLog
{
    public function record(SmsMessage $sms, MessageEvent $event, array $context = []): void;

    public function recordUnmatched(MessageEvent $event, array $payload, array $context = []): void;
}
