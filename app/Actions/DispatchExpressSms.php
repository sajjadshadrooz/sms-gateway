<?php

namespace App\Actions;

use App\Models\SmsMessage;
use App\Services\SmsOperator\SmsOperatorClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DispatchExpressSms
{
    public function __construct(
        private readonly SmsOperatorClient $operator,
    ) {}

    public function handle(SmsMessage $sms): void
    {
        try {
            $response = $this->operator->send([
                'id' => $sms->id,
                'to' => $sms->recipient,
                'message' => $sms->body,
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('SMS operator connection failed', [
                'sms_id' => $sms->id,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'SMS operator connection failed.',
                previous: $exception,
            );
        }

        if ($response->successful()) {
            $sms->update([
                'status' => 'accepted',
            ]);

            Log::info('SMS accepted by operator', [
                'sms_id' => $sms->id,
            ]);

            return;
        }

        if (
            $response->status() === 429 ||
            $response->serverError()
        ) {
            throw new RuntimeException(
                "Retryable operator error: HTTP {$response->status()}",
            );
        }

        $sms->update([
            'status' => 'failed',
        ]);

        Log::error('SMS rejected by operator', [
            'sms_id' => $sms->id,
            'http_status' => $response->status(),
            'response' => $response->json(),
        ]);
    }
}