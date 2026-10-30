<?php

namespace App\Actions;

use App\Contracts\MessageEventLog;
use App\Contracts\SmsQueue;
use App\Enums\MessageEvent;
use App\Enums\SmsType;
use App\Exceptions\InsufficientBalanceException;
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

            $message = $consumer->smsMessages()->create([
                'wallet_reservation_id' => $reservation->id,
                'type' => $type,
                'recipient' => $recipient,
                'body' => $body,
                'cost' => $cost,
            ]);

            $this->queue->publish($message);

            return $message;
        });

        // After the commit, so the history never shows a message that was rolled back.
        $this->events->record($message, MessageEvent::Queued);

        return $message;
    }
}
