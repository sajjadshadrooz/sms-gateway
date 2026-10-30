<?php

namespace App\Console\Commands;

use App\Actions\DispatchExpressSms;
use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Enums\MessageEvent;
use App\Enums\SmsMessageStatus;
use App\Models\SmsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Throwable;

class ConsumeExpressSms extends Command
{
    protected $signature = 'kafka:consume-express';

    protected $description = 'Consume Express SMS messages from Kafka';

    public function handle(DispatchExpressSms $dispatch, SmsQueue $queue, MessageEventLog $events): int
    {
        $topic = config('kafka.topics.express');

        $this->info("Listening to Kafka topic: {$topic}");

        Kafka::consumer([$topic])
            ->withBrokers(config('kafka.brokers'))
            ->withConsumerGroupId('sms-express-consumer')
            ->withManualCommit()
            // An uncaught exception stops the worker with the offset uncommitted, so the
            // message is reprocessed after restart instead of being skipped by a later commit.
            ->stopOnFailure()
            ->withHandler(function ($message, $consumer) use ($dispatch, $queue, $events) {
                $payload = $message->getBody();
                $payload = is_array($payload) ? $payload : ['raw' => $payload];

                $smsId = $payload['id'] ?? null;
                $sms = $smsId ? SmsMessage::find($smsId) : null;

                // Nothing in the database to settle: park the payload and move on.
                if ($sms === null) {
                    Log::error('Express SMS message does not match a stored message', [
                        'sms_id' => $smsId,
                    ]);

                    $queue->publishDeadLetter($payload, 'unknown_message');
                    $events->recordUnmatched(MessageEvent::UnknownMessage, $payload, [
                        'reason' => 'unknown_message',
                        'topic' => $message->getTopicName(),
                        'partition' => $message->getPartition(),
                        'offset' => $message->getOffset(),
                    ]);
                    $consumer->commit($message);

                    return;
                }

                if ($sms->status === SmsMessageStatus::Queued) {
                    $sms->update(['status' => SmsMessageStatus::Processing]);

                    $events->record($sms, MessageEvent::Processing, [
                        'topic' => $message->getTopicName(),
                        'partition' => $message->getPartition(),
                        'offset' => $message->getOffset(),
                    ]);
                }

                try {
                    $dispatch->handle($sms);
                } catch (Throwable $exception) {
                    Log::error('Express SMS processing failed', [
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
