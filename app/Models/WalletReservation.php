<?php

namespace App\Models;

use App\Enums\WalletReservationStatus;
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
}
