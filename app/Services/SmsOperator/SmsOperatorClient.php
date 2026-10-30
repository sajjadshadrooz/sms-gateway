<?php

namespace App\Services\SmsOperator;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SmsOperatorClient
{
    /**
     * A single attempt, on purpose: transient failures are retried later by
     * sms:republish-retries so a slow operator never blocks the Kafka worker.
     *
     * @param  array{id: int|string, to: string, message: string}  $payload
     *
     * @throws ConnectionException
     */
    public function send(array $payload): Response
    {
        return Http::acceptJson()
            ->withHeaders(['Idempotency-Key' => (string) $payload['id']])
            ->connectTimeout(2)
            ->timeout(5)
            ->post(config('services.sms_operator.url'), [
                'message_id' => $payload['id'],
                'to' => $payload['to'],
                'message' => $payload['message'],
            ]);
    }
}
