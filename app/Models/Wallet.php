<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['consumer_id', 'balance', 'reserved_balance'])]
class Wallet extends Model
{
    use HasFactory;

    protected $attributes = [
        'balance' => 0,
        'reserved_balance' => 0,
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'reserved_balance' => 'integer',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(WalletReservation::class);
    }

    public function availableBalance(): int
    {
        return $this->balance - $this->reserved_balance;
    }
}
