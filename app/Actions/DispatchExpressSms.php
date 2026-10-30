<?php

namespace App\Actions;

use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Enums\MessageEvent;
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
        private readonly MessageEventLog $events,
    ) {}

    /**
     * Decide from the current status so a redelivered Kafka message is harmless.
     */
    public function handle(SmsMessage $sms): void
    {
        $action = match ($sms->status) {
            SmsMessageStatus::Queued,
            SmsMessageStatus::Processing => null,
            SmsMessageStatus::Accepted => 'confirm_reservation',
            SmsMessageStatus::Failed => 'release_reservation',
            SmsMessageStatus::RetryScheduled,
            SmsMessageStatus::Delivered => 'ignore',
        };

        if ($action === null) {
            $this->send($sms);

            return;
        }

        match ($action) {
            'confirm_reservation' => $sms->reservation->confirm("SMS {$sms->id}"),
            'release_reservation' => $sms->reservation->release(),
            'ignore' => null,
        };

        $this->events->record($sms, MessageEvent::Skipped, ['action' => $action]);
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

            $this->events->record($sms, MessageEvent::Accepted, [
                'http_status' => $response->status(),
                'operator_response' => $response->json(),
            ]);

            Log::info('SMS accepted by operator', [
                'sms_id' => $sms->id,
                'attempts' => $sms->attempts,
            ]);

            return;
        }

        if ($response->status() === 429 || $response->serverError()) {
            $this->scheduleRetry($sms, "http {$response->status()}", $response->status());

            return;
        }

        $this->fail($sms, "http {$response->status()}", context: [
            'reason' => 'operator_rejected',
            'http_status' => $response->status(),
            'operator_response' => $response->json(),
        ]);

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
    private function scheduleRetry(SmsMessage $sms, string $error, int $httpStatus = 0): void
    {
        $attempt = $sms->attempts + 1;

        if ($attempt >= (int) config('sms.retry.max_attempts')) {
            $this->exhaust($sms, $attempt, $error, $httpStatus);

            return;
        }

        $nextRetryAt = now()->addSeconds($this->backoffFor($attempt));

        $sms->update([
            'status' => SmsMessageStatus::RetryScheduled,
            'attempts' => $attempt,
            'next_retry_at' => $nextRetryAt,
            'last_error' => $error,
        ]);

        $this->events->record($sms, MessageEvent::RetryScheduled, [
            'error' => $error,
            'http_status' => $httpStatus,
        ]);

        Log::warning('SMS retry scheduled', [
            'sms_id' => $sms->id,
            'attempt' => $attempt,
            'next_retry_at' => $nextRetryAt->toIso8601String(),
            'error' => $error,
        ]);
    }

    private function exhaust(SmsMessage $sms, int $attempt, string $error, int $httpStatus): void
    {
        $this->fail($sms, $error, $attempt, [
            'reason' => 'max_attempts_exceeded',
            'http_status' => $httpStatus,
        ]);

        // The database already reflects the failure; a DLQ outage must not undo that.
        try {
            $this->queue->publishDeadLetter([
                ...$this->queue->payloadFor($sms),
                'attempts' => $attempt,
                'last_error' => $error,
            ], 'max_attempts_exceeded');

            $this->events->record($sms, MessageEvent::DeadLettered, [
                'reason' => 'max_attempts_exceeded',
                'error' => $error,
            ]);
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

    private function fail(SmsMessage $sms, string $error, ?int $attempt = null, array $context = []): void
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

        $this->events->record($sms, MessageEvent::Failed, ['error' => $error, ...$context]);
    }

    private function backoffFor(int $attempt): int
    {
        $backoff = config('sms.retry.backoff_seconds', [5]);

        return (int) ($backoff[$attempt - 1] ?? end($backoff));
    }
}
