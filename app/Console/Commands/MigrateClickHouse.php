<?php

namespace App\Console\Commands;

use App\Services\ClickHouse\ClickHouseClient;
use Illuminate\Console\Command;
use Throwable;

class MigrateClickHouse extends Command
{
    protected $signature = 'clickhouse:migrate';

    protected $description = 'Create the ClickHouse database and the SMS message event table if they do not exist';

    public function handle(ClickHouseClient $clickHouse): int
    {
        $database = $clickHouse->database();
        $table = (string) config('clickhouse.tables.message_events');

        try {
            $clickHouse->execute("CREATE DATABASE IF NOT EXISTS {$database}", withDatabase: false);
            $clickHouse->execute($this->messageEventsTable($database, $table));
        } catch (Throwable $exception) {
            $this->error("ClickHouse migration failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("ClickHouse table {$database}.{$table} is ready.");

        return self::SUCCESS;
    }

    private function messageEventsTable(string $database, string $table): string
    {
        $retentionDays = config('clickhouse.retention_days');

        $ttl = is_numeric($retentionDays) && (int) $retentionDays > 0
            ? sprintf('TTL toDateTime(occurred_at) + INTERVAL %d DAY', (int) $retentionDays)
            : '';

        return <<<SQL
            CREATE TABLE IF NOT EXISTS {$database}.{$table}
            (
                occurred_at   DateTime64(3, 'UTC'),
                event         LowCardinality(String),
                sms_id        UInt64,
                consumer_id   UInt64,
                type          LowCardinality(String),
                status        LowCardinality(String),
                recipient     String,
                body          String CODEC(ZSTD(3)),
                cost          UInt32,
                attempts      UInt16,
                next_retry_at Nullable(DateTime64(3, 'UTC')),
                queued_at     Nullable(DateTime64(3, 'UTC')),
                error         String,
                http_status   UInt16,
                context       String CODEC(ZSTD(3)),
                recorded_by   LowCardinality(String),

                INDEX idx_event event TYPE set(16) GRANULARITY 4,
                INDEX idx_consumer consumer_id TYPE minmax GRANULARITY 4
            )
            ENGINE = MergeTree
            PARTITION BY toYYYYMM(occurred_at)
            ORDER BY (sms_id, occurred_at)
            {$ttl}
            SQL;
    }
}
