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

    /**
     * Queued messages untouched for longer than the given number of seconds:
     * most likely never published, or lost before a worker claimed them.
     */
    #[Scope]
    protected function staleQueued(Builder $query, int $seconds): Builder
    {
        return $query
            ->where('status', SmsMessageStatus::Queued)
            ->where('updated_at', '<=', now()->subSeconds($seconds));
    }

    /**
     * Move a queued message to processing, atomically: false when someone else
     * changed its status first (another delivery, or the stalled-message sweep).
     * The model is refreshed either way so callers act on the current status.
     */
    public function claimForProcessing(): bool
    {
        $claimed = static::query()
            ->whereKey($this->getKey())
            ->where('status', SmsMessageStatus::Queued)
            ->update(['status' => SmsMessageStatus::Processing, 'updated_at' => now()]) === 1;

        $this->refresh();

        return $claimed;
    }
}
