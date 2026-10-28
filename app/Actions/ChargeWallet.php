<?php

namespace App\Actions;

use App\Enums\WalletTransactionType;
use App\Models\Consumer;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class ChargeWallet
{
    public function handle(Consumer $consumer, int $amount, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($consumer, $amount, $description) {
            $wallet = $consumer->wallet()->lockForUpdate()->first()
                ?? $consumer->wallet()->create();

            $wallet->increment('balance', $amount);

            return $wallet->transactions()->create([
                'type' => WalletTransactionType::Credit,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => $description,
            ]);
        });
    }
}
