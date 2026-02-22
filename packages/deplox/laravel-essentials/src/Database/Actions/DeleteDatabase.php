<?php

declare(strict_types=1);

namespace Deplox\Essentials\Database\Actions;

use Illuminate\Container\Attributes\DB;
use Illuminate\Database\Connection;

final readonly class DeleteDatabase
{
    public function __construct(
        #[DB] private Connection $connection,
    ) {}

    public function __invoke(string $databaseName): void
    {
        $defaultConnectionDriver = $this->connection->getDriverName();

        if ($defaultConnectionDriver === 'pgsql') {
            // terminate existing connections to target database
            $this->connection->statement(
                'SELECT pg_terminate_backend(pg_stat_activity.pid)
                FROM pg_stat_activity
                WHERE pg_stat_activity.datname = ?', [$databaseName]);
        }

        $this->connection->getSchemaBuilder()->dropDatabaseIfExists($databaseName);
    }
}
