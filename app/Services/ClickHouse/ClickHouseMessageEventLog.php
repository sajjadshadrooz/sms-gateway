<?php

namespace App\Services\ClickHouse;

use App\Contracts\MessageEventLog;
use App\Enums\MessageEvent;
use App\Models\SmsMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClickHouseMessageEventLog implements MessageEventLog
{
    public function __construct(private readonly ClickHouseClient $clickHouse) {}

    public function record(SmsMessage $sms, MessageEvent $event, array $context = []): void
    {
        $this->insert([
            'occurred_at' => $this->timestamp(now()),
            'event' => $event->value,
            'sms_id' => (int) $sms->id,
            'consumer_id' => (int) $sms->consumer_id,
            'type' => $sms->type->value,
            'status' => $sms->status->value,
            'recipient' => (string) $sms->recipient,
            'body' => (string) $sms->body,
            'cost' => (int) $sms->cost,
            'attempts' => (int) $sms->attempts,
            'next_retry_at' => $this->timestamp($sms->next_retry_at),
            'queued_at' => $this->timestamp($sms->created_at),
            'error' => (string) ($context['error'] ?? $sms->last_error ?? ''),
            'http_status' => (int) ($context['http_status'] ?? 0),
            'context' => $this->encode($context),
            'recorded_by' => $this->recordedBy(),
        ]);
    }

    public function recordUnmatched(MessageEvent $event, array $payload, array $context = []): void
    {
        $smsId = $payload['id'] ?? null;

        $this->insert([
            'occurred_at' => $this->timestamp(now()),
            'event' => $event->value,
            'sms_id' => is_numeric($smsId) ? (int) $smsId : 0,
            'consumer_id' => (int) ($payload['consumer_id'] ?? 0),
            'type' => (string) ($payload['type'] ?? ''),
            'status' => '',
            'recipient' => (string) ($payload['to'] ?? ''),
            'body' => (string) ($payload['message'] ?? ''),
            'cost' => (int) ($payload['cost'] ?? 0),
            'attempts' => (int) ($payload['attempts'] ?? 0),
            'next_retry_at' => null,
            'queued_at' => null,
            'error' => (string) ($context['error'] ?? ''),
            'http_status' => (int) ($context['http_status'] ?? 0),
            'context' => $this->encode([...$context, 'payload' => $payload]),
            'recorded_by' => $this->recordedBy(),
        ]);
    }

    /**
     * The history must never take the sending pipeline down with it.
     *
     * @param  array<string, mixed>  $row
     */
    private function insert(array $row): void
    {
        try {
            $this->clickHouse->insert((string) config('clickhouse.tables.message_events'), [$row]);
        } catch (Throwable $exception) {
            Log::warning('SMS message event could not be written to ClickHouse', [
                'event' => $row['event'],
                'sms_id' => $row['sms_id'],
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function timestamp(?CarbonInterface $time): ?string
    {
        return $time?->copy()->utc()->format('Y-m-d H:i:s.v');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function encode(array $context): string
    {
        return json_encode((object) $context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }

    private function recordedBy(): string
    {
        return sprintf('%s#%d', gethostname() ?: 'unknown', getmypid());
    }
}
