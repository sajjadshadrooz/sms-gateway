<?php

namespace App\Actions;

use App\Contracts\SmsQueue;
use App\Enums\SmsMessageStatus;
use App\Models\SmsMessage;
use App\Services\SmsOperator\SmsOperatorClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchExpressSms
{
    public function __construct(
        private readonly SmsOperatorClient $operator,
        private readonly SmsQueue $queue,
    ) {}

    /**
     * Decide from the current status so a redelivered Kafka message is harmless.
     */
    public function handle(SmsMessage $sms): void
    {
        match ($sms->status) {
            SmsMessageStatus::Accepted => $sms->reservation->confirm("SMS {$sms->id}"),
            SmsMessageStatus::Failed => $sms->reservation->release(),
            // Waiting for sms:republish-retries; the original Kafka message is stale.
            SmsMessageStatus::RetryScheduled,
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
            $this->scheduleRetry($sms, "connection: {$exception->getMessage()}");

            return;
        }

        if ($response->successful()) {
            DB::transaction(function () use ($sms) {
                $sms->update([
                    'status' => SmsMessageStatus::Accepted,
                    'attempts' => $sms->attempts + 1,
                    'next_retry_at' => null,
                ]);
                $sms->reservation->confirm("SMS {$sms->id}");
            });

            Log::info('SMS accepted by operator', [
                'sms_id' => $sms->id,
                'attempts' => $sms->attempts,
            ]);

            return;
        }

        if ($response->status() === 429 || $response->serverError()) {
            $this->scheduleRetry($sms, "http {$response->status()}");

            return;
        }

        $this->fail($sms, "http {$response->status()}");

        Log::error('SMS rejected by operator', [
            'sms_id' => $sms->id,
            'http_status' => $response->status(),
            'response' => $response->json(),
        ]);
    }

    /**
     * Record the failed attempt and hand the message to the republish scheduler,
     * or give up once the attempt limit is reached.
     */
    private function scheduleRetry(SmsMessage $sms, string $error): void
    {
        $attempt = $sms->attempts + 1;

        if ($attempt >= (int) config('sms.retry.max_attempts')) {
            $this->exhaust($sms, $attempt, $error);

            return;
        }

        $nextRetryAt = now()->addSeconds($this->backoffFor($attempt));

        $sms->update([
            'status' => SmsMessageStatus::RetryScheduled,
            'attempts' => $attempt,
            'next_retry_at' => $nextRetryAt,
            'last_error' => $error,
        ]);

        Log::warning('SMS retry scheduled', [
            'sms_id' => $sms->id,
            'attempt' => $attempt,
            'next_retry_at' => $nextRetryAt->toIso8601String(),
            'error' => $error,
        ]);
    }

    private function exhaust(SmsMessage $sms, int $attempt, string $error): void
    {
        $this->fail($sms, $error, $attempt);

        // The database already reflects the failure; a DLQ outage must not undo that.
        try {
            $this->queue->publishDeadLetter([
                ...$this->queue->payloadFor($sms),
                'attempts' => $attempt,
                'last_error' => $error,
            ], 'max_attempts_exceeded');
        } catch (Throwable $exception) {
            Log::error('SMS could not be sent to the dead letter topic', [
                'sms_id' => $sms->id,
                'error' => $exception->getMessage(),
            ]);
        }

        Log::error('SMS gave up after max attempts', [
            'sms_id' => $sms->id,
            'attempts' => $attempt,
            'error' => $error,
        ]);
    }

    /**
     * Mark the message failed and return the held funds to the wallet, atomically.
     */
    private function fail(SmsMessage $sms, string $error, ?int $attempt = null): void
    {
        DB::transaction(function () use ($sms, $error, $attempt) {
            $sms->update([
                'status' => SmsMessageStatus::Failed,
                'attempts' => $attempt ?? $sms->attempts + 1,
                'next_retry_at' => null,
                'last_error' => $error,
            ]);
            $sms->reservation->release();
        });
    }

    private function backoffFor(int $attempt): int
    {
        /** @var list<int> $backoff */
        $backoff = config('sms.retry.backoff_seconds', [5]);

        return (int) ($backoff[$attempt - 1] ?? end($backoff));
    }
}
