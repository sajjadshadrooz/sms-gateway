<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeExpressSms extends Command
{
    protected $signature = 'kafka:consume-express';

    protected $description = 'Consume Express SMS messages from Kafka';

    public function handle(): int
    {
        $topic = config('kafka.topics.express');

        $this->info("Listening to Kafka topic: {$topic}");

        Kafka::consumer([$topic])
            ->withBrokers(config('kafka.brokers'))
            ->withConsumerGroupId('sms-express-consumer')
            ->withHandler(function ($message) {
                $this->info('Message received:');

                $this->line(json_encode(
                    $message->getBody(),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                ));
            })
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}