<?php

namespace App\Models;

use App\Enums\SmsMessageStatus;
use App\Enums\SmsType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consumer_id',
    'wallet_reservation_id',
    'type',
    'recipient',
    'body',
    'cost',
    'status',
    'attempts',
    'next_retry_at',
    'last_error',
])]
class SmsMessage extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => SmsMessageStatus::Queued,
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => SmsType::class,
            'status' => SmsMessageStatus::class,
            'cost' => 'integer',
            'attempts' => 'integer',
            'next_retry_at' => 'datetime',
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

    /**
     * Messages waiting for a retry whose scheduled time has arrived.
     */
    #[Scope]
    protected function retryDue(Builder $query): Builder
    {
        return $query
            ->where('status', SmsMessageStatus::RetryScheduled)
            ->where('next_retry_at', '<=', now());
    }
}
