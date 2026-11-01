<?php

namespace App\Console\Commands;

use App\Services\ClickHouse\MessageEventWriter;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;

class WriteMessageEvents extends Command
{
    protected $signature = 'clickhouse:write-events';

    protected $description = 'Write SMS message events from Kafka to ClickHouse in batches';

    public function handle(MessageEventWriter $writer): int
    {
        $topic = (string) config('kafka.topics.message_events');

        $this->info("Writing message events from Kafka topic: {$topic}");

        $consumer = Kafka::consumer([$topic])
            ->withBrokers(config('kafka.brokers'))
            ->withConsumerGroupId('sms-message-event-writer')
            ->withManualCommit()
            ->withOption('auto.offset.reset', 'earliest')
            ->stopOnFailure()
            ->withHandler(function (ConsumerMessage $message, MessageConsumer $consumer) use ($writer) {
                $writer->add($message);

                if ($writer->isFull()) {
                    $this->flush($writer, $consumer);
                }
            })
            ->afterConsuming(function (MessageConsumer $consumer) use ($writer) {
                if ($writer->isDue()) {
                    $this->flush($writer, $consumer);
                }
            })
            ->build();

        $consumer->consume();
        
        $this->flush($writer, $consumer);

        return self::SUCCESS;
    }

    private function flush(MessageEventWriter $writer, MessageConsumer $consumer): void
    {
        $rows = $writer->count();
        $offsets = $writer->flush();

        if ($offsets !== []) {
            $consumer->commit($offsets);
        }

        if ($rows > 0) {
            $this->info("Wrote {$rows} message events");
        }
    }
}
