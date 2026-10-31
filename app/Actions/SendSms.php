<?php

namespace App\Actions;

use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Enums\MessageEvent;
use App\Enums\SmsMessageStatus;
use App\Enums\SmsType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsQueueUnavailableException;
use App\Models\Consumer;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * proccess the sms and publish in queue.
 **/
class SendSms
{
    public function __construct(
        private readonly SmsQueue $queue,
        private readonly MessageEventLog $events,
    ) {}

    public function handle(Consumer $consumer, SmsType $type, string $recipient, string $body): SmsMessage
    {
        $cost = $type->price();

        $message = DB::transaction(function () use ($consumer, $type, $recipient, $body, $cost) {
            $wallet = $consumer->wallet()->lockForUpdate()->first();
            $available = $wallet?->availableBalance() ?? 0;

            if ($wallet === null || $available < $cost) {
                throw new InsufficientBalanceException(required: $cost, available: max(0, $available));
            }

            $wallet->increment('reserved_balance', $cost);

            $reservation = $wallet->reservations()->create([
                'amount' => $cost,
                'reference_id' => (string) Str::ulid(),
                'expires_at' => now()->addMinutes((int) config('sms.reservation_ttl_minutes')),
            ]);

            return $consumer->smsMessages()->create([
                'wallet_reservation_id' => $reservation->id,
                'type' => $type,
                'recipient' => $recipient,
                'body' => $body,
                'cost' => $cost,
            ]);
        });

        // Only after the commit: a worker that consumes the message before the row
        // is visible would find nothing and dead-letter it.
        try {
            $this->queue->publish($message);
        } catch (SmsQueueUnavailableException $exception) {
            $this->abandon($message, $exception);

            throw $exception;
        }

        $this->events->record($message, MessageEvent::Queued);

        return $message;
    }

    /**
     * The caller is told to try again, so the message must not be sent later on
     * its own: fail it and give the reserved funds back.
     */
    private function abandon(SmsMessage $message, SmsQueueUnavailableException $exception): void
    {
        DB::transaction(function () use ($message) {
            $message->update([
                'status' => SmsMessageStatus::Failed,
                'last_error' => 'queue_unavailable',
            ]);
            $message->reservation->release();
        });

        $this->events->record($message, MessageEvent::Failed, [
            'reason' => 'queue_unavailable',
            'error' => $exception->getMessage(),
        ]);
    }
}
