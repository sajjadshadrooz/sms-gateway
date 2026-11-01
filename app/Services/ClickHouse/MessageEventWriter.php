<?php

namespace App\Services\ClickHouse;

use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use RdKafka\TopicPartition;
use Throwable;
class MessageEventWriter
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, TopicPartition> */
    private array $offsets = [];

    private ?float $startedAt = null;

    public function __construct(private readonly ClickHouseClient $clickHouse) {}

    public function add(ConsumerMessage $message): void
    {
        $row = $message->getBody();

        if (is_array($row) && isset($row['event'], $row['occurred_at'])) {
            $this->rows[] = $row;
        } else {
            Log::warning('Malformed SMS message event skipped', [
                'partition' => $message->getPartition(),
                'offset' => $message->getOffset(),
            ]);
        }

        $key = $message->getTopicName().':'.$message->getPartition();
        $this->offsets[$key] = new TopicPartition($message->getTopicName(), $message->getPartition(), $message->getOffset() + 1);
        $this->startedAt ??= microtime(true);
    }

    public function isFull(): bool
    {
        return count($this->rows) >= max(1, (int) config('clickhouse.writer.batch_size'));
    }

    public function isDue(): bool
    {
        return $this->startedAt !== null
            && (microtime(true) - $this->startedAt) * 1000 >= (int) config('clickhouse.writer.flush_interval_ms');
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function flush(): array
    {
        if ($this->rows !== []) {
            $this->insert();
        }

        $offsets = array_values($this->offsets);

        $this->rows = [];
        $this->offsets = [];
        $this->startedAt = null;

        return $offsets;
    }

    private function insert(): void
    {
        $attempts = max(1, (int) config('clickhouse.writer.insert_attempts'));
        $backoff = (int) config('clickhouse.writer.retry_backoff_ms');

        retry($attempts, function (int $attempt) use ($attempts) {
            try {
                $this->clickHouse->insert((string) config('clickhouse.tables.message_events'), $this->rows);
            } catch (Throwable $exception) {
                Log::warning('SMS message events could not be written to ClickHouse', [
                    'rows' => count($this->rows),
                    'attempt' => $attempt,
                    'attempts' => $attempts,
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }, fn (int $attempt) => $backoff * 2 ** ($attempt - 1));
    }
}
