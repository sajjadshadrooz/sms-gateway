<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

class DatabaseReadiness
{
    public function __construct(
        private readonly ConnectionResolverInterface $connections,
    ) {}

    // checking if database is up: when return true database is ok.
    public function isReady(?string $connection = null): bool
    {
        return $this->failure($connection) === null;
    }

    // checking if database is down: when return null database is ok when return faiure it has a problem.
    public function failure(?string $connection = null): ?string
    {
        try 
        {
            $db = $this->connections->connection($connection);

            assert($db instanceof Connection);

            $db->select('select 1');
            $db->disconnect();
            return null;
        } 
        catch (Throwable $e)
        {
            return $e->getMessage();
        }
    }
}
