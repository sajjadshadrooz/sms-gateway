<?php

namespace App\Console\Commands;

use App\Actions\DispatchSms;
use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Enums\MessageEvent;
use App\Enums\SmsMessageStatus;
use App\Enums\SmsType;
use App\Models\SmsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Throwable;

/**
 * Consumes the Kafka topic of one SMS type. Each type has its own consumer group,
 * so normal traffic never holds up express workers and the other way round.
 */
abstract class ConsumeSmsTopic extends Command
{
    abstract protected function type(): SmsType;

    public function handle(DispatchSms $dispatch, SmsQueue $queue, MessageEventLog $events): int
    {
        $type = $this->type();
        $topic = $type->topic();

        $this->info("Listening to Kafka topic: {$topic}");

        Kafka::consumer([$topic])
            ->withBrokers(config('kafka.brokers'))
            ->withConsumerGroupId("sms-{$type->value}-consumer")
            ->withManualCommit()
            // An uncaught exception stops the worker with the offset uncommitted, so the
            // message is reprocessed after restart instead of being skipped by a later commit.
            ->stopOnFailure()
            ->withHandler(function ($message, $consumer) use ($type, $dispatch, $queue, $events) {
                $payload = $message->getBody();
                $payload = is_array($payload) ? $payload : ['raw' => $payload];

                $smsId = $payload['id'] ?? null;
                $sms = $smsId ? SmsMessage::find($smsId) : null;

                // Nothing in the database to settle: park the payload and move on.
                if ($sms === null) {
                    Log::error('SMS message does not match a stored message', [
                        'type' => $type->value,
                        'sms_id' => $smsId,
                    ]);

                    // The type picks the dead letter topic.
                    $queue->publishDeadLetter(['type' => $type->value, ...$payload], 'unknown_message');
                    $events->recordUnmatched(MessageEvent::UnknownMessage, $payload, [
                        'reason' => 'unknown_message',
                        'topic' => $message->getTopicName(),
                        'partition' => $message->getPartition(),
                        'offset' => $message->getOffset(),
                    ]);
                    $consumer->commit($message);

                    return;
                }

                if ($sms->status === SmsMessageStatus::Queued && $sms->claimForProcessing()) {
                    $events->record($sms, MessageEvent::Processing, [
                        'topic' => $message->getTopicName(),
                        'partition' => $message->getPartition(),
                        'offset' => $message->getOffset(),
                    ]);
                }

                try {
                    $dispatch->handle($sms);
                } catch (Throwable $exception) {
                    Log::error('SMS processing failed', [
                        'type' => $type->value,
                        'sms_id' => $smsId,
                        'error' => $exception->getMessage(),
                    ]);

                    throw $exception;
                }

                $consumer->commit($message);

                $this->info("Processed SMS: {$smsId}");
            })
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}
