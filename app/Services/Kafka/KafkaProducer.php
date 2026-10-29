<?php

namespace App\Services\Kafka;

use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;

class KafkaProducer
{
    private ?Producer $producer = null;

    public function send(string $topic, string $value, ?string $key = null, array $headers = []): void
    {
        $this->producer()->send($topic, $value, $key, $headers);
    }

    public function close(): void
    {
        $this->producer?->close();
        $this->producer = null;
    }

    private function producer(): Producer
    {
        if ($this->producer === null) {
            $config = new ProducerConfig;
            $config->setBootstrapServer((string) config('kafka.brokers'));
            $config->setClientId((string) config('kafka.client_id'));
            $config->setUpdateBrokers(true);
            $config->setAcks(-1);
            $config->setAutoCreateTopic(true); // in future will be change.
            $config->setConnectTimeout((float) config('kafka.connect_timeout'));
            $config->setSendTimeout((float) config('kafka.send_timeout'));
            $config->setRecvTimeout((float) config('kafka.recv_timeout'));

            $this->producer = new Producer($config);
        }

        return $this->producer;
    }
}
