<?php

namespace App\Services;

use RdKafka\Conf;
use RdKafka\Producer;
use Throwable;

class KafkaReadiness
{
    public function isReady(): bool
    {
        return $this->failure() === null;
    }

    public function failure(): ?string
    {
        $timeoutMs = (int) round((float) config('kafka.connect_timeout') * 1000);

        $conf = new Conf;
        $conf->set('bootstrap.servers', (string) config('kafka.brokers'));
        $conf->set('client.id', config('kafka.client_id').'-readiness');
        $conf->set('socket.connection.setup.timeout.ms', (string) $timeoutMs);
        $conf->set('socket.timeout.ms', (string) max(10, $timeoutMs));

        try {
            $metadata = (new Producer($conf))->getMetadata(false, null, $timeoutMs);

            if ($metadata->getBrokers()->count() === 0) {
                return 'No Kafka broker answered the metadata request.';
            }

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
