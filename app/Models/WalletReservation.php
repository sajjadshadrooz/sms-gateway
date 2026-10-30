<?php

namespace App\Models;

use App\Enums\WalletReservationStatus;
use App\Enums\WalletTransactionType;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['wallet_id', 'amount', 'status', 'reference_id', 'expires_at'])]
class WalletReservation extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => WalletReservationStatus::Pending,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => WalletReservationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', WalletReservationStatus::Pending);
    }

    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query->pending()->where('expires_at', '<=', now());
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function release(): void
    {
        $this->settle(WalletReservationStatus::Released, function (Wallet $wallet, self $reservation) {
            $wallet->decrement('reserved_balance', $reservation->amount);
        });
    }

    public function confirm(?string $description = null): void
    {
        $this->settle(WalletReservationStatus::Confirmed, function (Wallet $wallet, self $reservation) use ($description) {
            $wallet->decrement('reserved_balance', $reservation->amount);
            $wallet->decrement('balance', $reservation->amount);

            $wallet->transactions()->create([
                'type' => WalletTransactionType::Debit,
                'amount' => $reservation->amount,
                'balance_after' => $wallet->balance,
                'description' => $description,
            ]);
        });
    }

    private function settle(WalletReservationStatus $target, Closure $apply): void
    {
        DB::transaction(function () use ($target, $apply) {
            
            $reservation = self::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($reservation->status === WalletReservationStatus::Pending) {

                $wallet = $reservation->wallet()->lockForUpdate()->firstOrFail();

                $apply($wallet, $reservation);

                $reservation->update(['status' => $target]);
            }

            $this->setRawAttributes($reservation->getAttributes(), true);
        });
    }
}
