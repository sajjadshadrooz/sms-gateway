<?php

namespace App\Models;

use App\Enums\SmsMessageStatus;
use App\Enums\SmsType;
use Database\Factories\SmsMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['consumer_id', 'wallet_reservation_id', 'type', 'recipient', 'body', 'cost', 'status'])]
class SmsMessage extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => SmsMessageStatus::Queued,
    ];

    protected function casts(): array
    {
        return [
            'type' => SmsType::class,
            'status' => SmsMessageStatus::class,
            'cost' => 'integer',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(WalletReservation::class, 'wallet_reservation_id');
    }
}
