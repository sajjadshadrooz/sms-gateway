<?php

namespace App\Services\Kafka;

use App\Contracts\SmsQueue;
use App\Enums\SmsType;
use App\Exceptions\SmsQueueUnavailableException;
use App\Models\SmsMessage;
use Throwable;

class KafkaSmsQueue implements SmsQueue
{
    public function __construct(private readonly KafkaProducer $producer) {}

    public function publish(SmsMessage $message): void
    {
        $topic = $message->type->topic();

        $this->send($topic, $this->payloadFor($message), $this->partitionKey($message));
    }

    public function publishDeadLetter(array $payload, string $reason): void
    {
        // Payloads from before the type was included went to the express topic.
        $type = SmsType::tryFrom((string) ($payload['type'] ?? '')) ?? SmsType::Express;
        $topic = $type->deadLetterTopic();

        $key = isset($payload['id']) ? "message-{$payload['id']}" : null;

        $this->send($topic, $payload, $key, [
            'reason' => $reason,
            'failed_at' => now()->toIso8601String(),
        ]);
    }

    public function payloadFor(SmsMessage $message): array
    {
        return [
            'id' => $message->id,
            'consumer_id' => $message->consumer_id,
            'type' => $message->type->value,
            'to' => $message->recipient,
            'message' => $message->body,
            'cost' => $message->cost,
            'wallet_reservation_id' => $message->wallet_reservation_id,
            'accepted_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function send(string $topic, array $payload, ?string $key, array $headers = []): void
    {
        $value = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        try {
            $this->producer->send($topic, $value, $key, $headers);
        } catch (Throwable $e) {
            throw new SmsQueueUnavailableException($topic, $e);
        }
    }

    /**
     * Express messages get a unique key so they hash evenly across all partitions.
     * Normal messages are keyed by consumer so ordering per consumer is preserved.
     * Prefixes keep the two key spaces from colliding when an id value matches.
     */
    private function partitionKey(SmsMessage $message): string
    {
        return match ($message->type) {
            SmsType::Express => "message-{$message->id}",
            SmsType::Normal => "consumer-{$message->consumer_id}",
        };
    }
}
