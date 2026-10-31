<?php

namespace App\Services\ClickHouse;

use App\Enums\MessageEvent;
use App\Exceptions\MessageHistoryUnavailableException;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class ClickHouseMessageHistory
{
    public const VISIBLE_EVENTS = [
        MessageEvent::Queued,
        MessageEvent::Processing,
        MessageEvent::RetryScheduled,
        MessageEvent::Republished,
        MessageEvent::Accepted,
        MessageEvent::Failed,
    ];

    private const COLUMNS = <<<'SQL'
        occurred_at, event, sms_id, type, status, recipient, attempts, next_retry_at,
        error, http_status, JSONExtractString(context, 'reason') AS reason
        SQL;

    public function __construct(private readonly ClickHouseClient $clickHouse) {}

    public function forConsumer(int $consumerId, array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $conditions = ['consumer_id = {consumer_id:UInt64}', 'has({events:Array(String)}, event)'];
        $bindings = ['consumer_id' => $consumerId, 'events' => $this->visibleEvents()];

        foreach ([
            'sms_id' => 'sms_id = {sms_id:UInt64}',
            'event' => 'event = {event:String}',
            'type' => 'type = {type:String}',
            'from' => "occurred_at >= {from:DateTime64(3, 'UTC')}",
            'to' => "occurred_at <= {to:DateTime64(3, 'UTC')}",
        ] as $filter => $condition) {
            if (isset($filters[$filter])) {
                $conditions[] = $condition;
                $bindings[$filter] = $filters[$filter];
            }
        }

        $where = implode(' AND ', $conditions);

        $total = (int) ($this->select("SELECT count() AS total FROM {$this->table()} WHERE {$where}", $bindings)[0]['total'] ?? 0);

        $rows = $total === 0 ? [] : $this->select(
            'SELECT '.self::COLUMNS." FROM {$this->table()} WHERE {$where}"
            .' ORDER BY occurred_at DESC, sms_id DESC LIMIT {limit:UInt32} OFFSET {offset:UInt64}',
            [...$bindings, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return new LengthAwarePaginator($rows, $total, $perPage, $page);
    }

    /**
     * @param  array<string, scalar>  $bindings
     * @return list<array<string, mixed>>
     */
    private function select(string $sql, array $bindings): array
    {
        try {
            return $this->clickHouse->select($sql, $bindings);
        } catch (Throwable $exception) {
            throw new MessageHistoryUnavailableException($exception);
        }
    }

    private function table(): string
    {
        return $this->clickHouse->database().'.'.config('clickhouse.tables.message_events');
    }
    private function visibleEvents(): string
    {
        return "['".implode("','", array_map(fn (MessageEvent $event) => $event->value, self::VISIBLE_EVENTS))."']";
    }
}
