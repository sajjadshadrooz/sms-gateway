<?php

namespace App\Models;

use App\Enums\WalletReservationStatus;
use Database\Factories\WalletReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

  
    //Reservations that still hold funds against their wallet.
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->where('status', WalletReservationStatus::Pending);
    }

    /**
     * Pending reservations whose expiry time has already passed.
     */
    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query->pending()->where('expires_at', '<=', now());
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
