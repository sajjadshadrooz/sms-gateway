<?php

namespace App\Services\Kafka;

use Junges\Kafka\Contracts\MessageSerializer;
use Junges\Kafka\Contracts\ProducerMessage;

class RawMessageSerializer implements MessageSerializer
{
    public function serialize(ProducerMessage $message): ProducerMessage
    {
        $body = $message->getBody();

        if (is_string($body)) {
            return $message;
        }

        return $message->withBody(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
