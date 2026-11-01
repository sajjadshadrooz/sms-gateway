<?php

namespace App\Services\ClickHouse;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ClickHouseClient
{
    public function execute(string $sql, bool $withDatabase = true): void
    {
        $this->request($withDatabase)
            ->withBody($sql, 'text/plain')
            ->post('')
            ->throw();
    }
    public function insert(string $table, array $rows): Response
    {
        $body = implode("\n", array_map(
            fn (array $row) => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $rows,
        ));

        $query = [
            'query' => sprintf('INSERT INTO %s.%s FORMAT JSONEachRow', $this->database(), $table),
        ];

        if (config('clickhouse.async_insert')) {
            $query['async_insert'] = 1;
            $query['wait_for_async_insert'] = 1;
        }

        return $this->request()
            ->withQueryParameters($query)
            ->withBody($body."\n", 'application/x-ndjson')
            ->post('')
            ->throw();
    }

    public function select(string $sql, array $bindings = []): array
    {
        $query = ['output_format_json_quote_64bit_integers' => 0];

        foreach ($bindings as $name => $value) {
            $query["param_{$name}"] = $value;
        }

        return $this->request()
            ->withQueryParameters($query)
            ->withBody($sql.' FORMAT JSON', 'text/plain')
            ->post('')
            ->throw()
            ->json('data', []);
    }

    public function database(): string
    {
        return (string) config('clickhouse.database');
    }

    private function request(bool $withDatabase = true): PendingRequest
    {
        $request = Http::baseUrl((string) config('clickhouse.url'))
            ->withHeaders([
                'X-ClickHouse-User' => (string) config('clickhouse.username'),
                'X-ClickHouse-Key' => (string) config('clickhouse.password'),
            ])
            ->connectTimeout((float) config('clickhouse.connect_timeout'))
            ->timeout((float) config('clickhouse.timeout'));

        if ($withDatabase) {
            $request = $request->withHeaders(['X-ClickHouse-Database' => $this->database()]);
        }

        return $request;
    }
}
