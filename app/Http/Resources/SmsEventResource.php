<?php

namespace App\Http\Resources;

use App\Enums\MessageEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One row of the ClickHouse message event log, as a consumer sees it.
 *
 * @property array<string, mixed> $resource
 */
class SmsEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sms_id' => (int) $this->resource['sms_id'],
            'event' => $this->resource['event'],
            'type' => $this->resource['type'],
            'status' => $this->resource['status'] ?: null,
            'to' => $this->resource['recipient'],
            'attempts' => (int) $this->resource['attempts'],
            'next_retry_at' => $this->time($this->resource['next_retry_at']),
            'reason' => $this->reason(),
            'http_status' => (int) $this->resource['http_status'] ?: null,
            'occurred_at' => $this->time($this->resource['occurred_at']),
        ];
    }

    private function reason(): ?string
    {
        if (! in_array($this->resource['event'], [MessageEvent::RetryScheduled->value, MessageEvent::Failed->value], true)) {
            return null;
        }

        if ($this->resource['reason'] !== '') {
            return $this->resource['reason'];
        }

        return match (true) {
            str_starts_with($this->resource['error'], 'connection') => 'operator_unreachable',
            str_starts_with($this->resource['error'], 'http ') => 'operator_error',
            default => null,
        };
    }

    private function time(?string $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, 'UTC');
    }
}
