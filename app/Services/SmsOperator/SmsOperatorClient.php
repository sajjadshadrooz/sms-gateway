<?php

namespace App\Services\SmsOperator;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SmsOperatorClient
{
    public function send(array $payload): Response
    {
        return Http::acceptJson()
            ->withHeaders(['Idempotency-Key' => (string) $payload['id']])
            ->connectTimeout(2)
            ->timeout(5)
            ->retry([1000, 3000], when: function (\Throwable $exception) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                return $exception instanceof RequestException
                    && ($exception->response->status() === 429 || $exception->response->serverError());
            }, throw: false)
            ->post(config('services.sms_operator.url'), [
                'message_id' => $payload['id'],
                'to' => $payload['to'],
                'message' => $payload['message'],
            ]);
    }
}
