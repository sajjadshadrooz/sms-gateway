<?php

namespace App\Services\Kafka;

use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\Producer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Junges\Kafka\Producers\Builder;
use RdKafka\Message as DeliveryReport;
use RdKafka\Producer as RdKafkaProducer;
use Throwable;

class KafkaEventProducer
{
    private ?Producer $producer = null;

    public function send(string $topic, string $value, ?string $key = null): void
    {
        $message = Message::create($topic)
            ->withBody($value)
            ->withKey($key);

        $this->producer()->produce($message);
    }

    //Hand whatever is still buffered to the broker before the process ends.
    public function flush(): void
    {
        try {
            $this->producer?->flush();
        } catch (Throwable $exception) {
            Log::warning('SMS message events could not be flushed to Kafka', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function producer(): Producer
    {
        if ($this->producer !== null) {
            return $this->producer;
        }

        $builder = Kafka::asyncPublish();

        assert($builder instanceof Builder);

        $builder
            ->withConfigOptions([
                'client.id' => config('kafka.client_id').'-events',
                'acks' => 'all',
                'enable.idempotence' => 'true',
                'linger.ms' => '20',
                'socket.connection.setup.timeout.ms' => $this->milliseconds('kafka.connect_timeout'),
                'message.timeout.ms' => $this->milliseconds('kafka.message_events_timeout'),
            ])
            ->usingSerializer(new RawMessageSerializer)
            ->withFlushTimeout((int) $this->milliseconds('kafka.send_timeout'))
            ->withFlushRetries(1);

        $builder->withDrMsgCb(function (RdKafkaProducer $kafka, DeliveryReport $report): void {
            if ($report->err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                Log::warning('SMS message event could not be delivered to Kafka', [
                    'topic' => $report->topic_name,
                    'key' => $report->key,
                    'error' => rd_kafka_err2str($report->err),
                ]);
            }
        });

        return $this->producer = $builder->build();
    }

    private function milliseconds(string $configKey): string
    {
        return (string) (int) round((float) config($configKey) * 1000);
    }
}
