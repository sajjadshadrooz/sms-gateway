<?php

namespace App\Actions;

use App\Enums\SmsMessageStatus;
use App\Models\SmsMessage;
use App\Services\SmsOperator\SmsOperatorClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DispatchExpressSms
{
    public function __construct(
        private readonly SmsOperatorClient $operator,
    ) {}

    public function handle(SmsMessage $sms): void
    {
        match ($sms->status) {
            SmsMessageStatus::Accepted => $sms->reservation->confirm("SMS {$sms->id}"),
            SmsMessageStatus::Failed => $sms->reservation->release(),
            SmsMessageStatus::Delivered => null,
            SmsMessageStatus::Queued,
            SmsMessageStatus::Processing => $this->send($sms),
        };
    }

    private function send(SmsMessage $sms): void
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
            DB::transaction(function () use ($sms) {
                $sms->update(['status' => SmsMessageStatus::Accepted]);
                $sms->reservation->confirm("SMS {$sms->id}");
            });

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

        DB::transaction(function () use ($sms) {
            $sms->update(['status' => SmsMessageStatus::Failed]);
            $sms->reservation->release();
        });

        Log::error('SMS rejected by operator', [
            'sms_id' => $sms->id,
            'http_status' => $response->status(),
            'response' => $response->json(),
        ]);
    }
}
