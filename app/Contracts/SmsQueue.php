<?php

namespace App\Contracts;

use App\Exceptions\SmsQueueUnavailableException;
use App\Models\SmsMessage;

interface SmsQueue
{
    /**
     * Put the message on the topic for its type.
     *
     * @throws SmsQueueUnavailableException
     */
    public function publish(SmsMessage $message): void;

    /**
     * Park a message that can no longer be processed, for later inspection or replay.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws SmsQueueUnavailableException
     */
    public function publishDeadLetter(array $payload, string $reason): void;

    /**
     * The wire representation of a message, as published to the topic.
     *
     * @return array<string, mixed>
     */
    public function payloadFor(SmsMessage $message): array;
}
