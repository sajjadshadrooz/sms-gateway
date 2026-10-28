<?php

namespace App\Services;

use longlang\phpkafka\Broker;
use longlang\phpkafka\Producer\ProducerConfig;
use Throwable;

class KafkaReadiness
{
    // checking if kafka is up: when return true at least one broker answered a metadata request.
    public function isReady(): bool
    {
        return $this->failure() === null;
    }

    // checking if kafka is down: when return null kafka is ok when return failure it has a problem.
    public function failure(): ?string
    {
        $config = new ProducerConfig;
        $config->setBootstrapServer((string) config('kafka.brokers'));
        $config->setClientId(config('kafka.client_id').'-readiness');
        $config->setConnectTimeout((float) config('kafka.connect_timeout'));
        $config->setSendTimeout((float) config('kafka.send_timeout'));
        $config->setRecvTimeout((float) config('kafka.recv_timeout'));

        $broker = new Broker($config);

        try {
            // Opens a fresh connection to a bootstrap server and asks the cluster for its broker list.
            $broker->updateBrokers();

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        } finally {
            $broker->close();
        }
    }
}
