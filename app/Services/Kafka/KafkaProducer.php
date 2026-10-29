<?php

namespace App\Services\Kafka;

use Junges\Kafka\Contracts\Producer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

use RdKafka\Message as DeliveryReport;
use RdKafka\Producer as RdKafkaProducer;

use RuntimeException;

class KafkaProducer
{
    private ?Producer $producer = null;

    private ?string $deliveryError = null;

    public function send(string $topic, string $value, ?string $key = null, array $headers = []): void
    {
        $this->deliveryError = null;

        $message = Message::create($topic)
            ->withBody($value)
            ->withKey($key)
            ->withHeaders($headers);

        $this->producer()->produce($message);

        if ($this->deliveryError !== null) {
            throw new RuntimeException("Kafka rejected the message: {$this->deliveryError}");
        }
    }

    public function close(): void
    {
        $this->producer?->flush();
        $this->producer = null;
    }

    private function producer(): Producer
    {
        return $this->producer ??= Kafka::publish()
            ->withConfigOptions([
                'client.id' => (string) config('kafka.client_id'),
                'acks' => 'all',
                'enable.idempotence' => 'true',
                'socket.connection.setup.timeout.ms' => $this->milliseconds('kafka.connect_timeout'),
                'socket.timeout.ms' => $this->milliseconds('kafka.recv_timeout'),
                'request.timeout.ms' => $this->milliseconds('kafka.recv_timeout'),
                'message.timeout.ms' => $this->milliseconds('kafka.send_timeout'),
            ])
            ->withDrMsgCb(function (RdKafkaProducer $kafka, DeliveryReport $report): void {
                if ($report->err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                    $this->deliveryError = rd_kafka_err2str($report->err);
                }
            })
            ->usingSerializer(new RawMessageSerializer)
            ->withFlushTimeout((int) $this->milliseconds('kafka.send_timeout'))
            ->withFlushRetries((int) config('kafka.flush_retries', 2))
            ->build();
    }

    private function milliseconds(string $configKey): string
    {
        return (string) (int) round((float) config($configKey) * 1000);
    }
}
