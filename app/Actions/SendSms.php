<?php

namespace App\Actions;

use App\Enums\SmsType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Consumer;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendSms
{
    public function handle(Consumer $consumer, SmsType $type, string $recipient, string $body): SmsMessage
    {
        $cost = $type->price();

        return DB::transaction(function () use ($consumer, $type, $recipient, $body, $cost) {
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
    }
}
