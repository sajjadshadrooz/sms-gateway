<?php

namespace App\Console\Commands;

use App\Actions\DispatchExpressSms;
use App\Models\SmsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Throwable;

class ConsumeExpressSms extends Command
{
    protected $signature = 'kafka:consume-express';

    protected $description = 'Consume Express SMS messages from Kafka';

    public function handle(DispatchExpressSms $dispatch): int
    {
        $topic = config('kafka.topics.express');

        $this->info("Listening to Kafka topic: {$topic}");

        Kafka::consumer([$topic])
            ->withBrokers(config('kafka.brokers'))
            ->withConsumerGroupId('sms-express-consumer')
            ->withManualCommit()
            ->withHandler(function ($message, $consumer) use ($dispatch) {
                $payload = $message->getBody();

                $smsId = $payload['id'] ?? null;

                if (! $smsId) {
                    Log::error('Invalid Express SMS message');

                    throw new \RuntimeException(
                        'Missing message_id in Kafka payload.'
                    );
                }

                $sms = SmsMessage::findOrFail($smsId);

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