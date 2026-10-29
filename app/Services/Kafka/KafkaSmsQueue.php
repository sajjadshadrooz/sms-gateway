<?php

namespace App\Services\Kafka;

use App\Contracts\SmsQueue;
use App\Exceptions\SmsQueueUnavailableException;
use App\Models\SmsMessage;
use Throwable;

class KafkaSmsQueue implements SmsQueue
{
    public function __construct(private readonly KafkaProducer $producer) {}

    public function publish(SmsMessage $message): void
    {
        $topic = $message->type->topic();

        $payload = json_encode([
            'id' => $message->id,
            'consumer_id' => $message->consumer_id,
            'type' => $message->type->value,
            'to' => $message->recipient,
            'message' => $message->body,
            'cost' => $message->cost,
            'wallet_reservation_id' => $message->wallet_reservation_id,
            'accepted_at' => $message->created_at?->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        try {
            $this->producer->send($topic, $payload, (string) $message->consumer_id);
        } catch (Throwable $e) {
            throw new SmsQueueUnavailableException($topic, $e);
        }
    }
}
