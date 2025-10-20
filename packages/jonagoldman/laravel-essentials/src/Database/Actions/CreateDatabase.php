<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Database\Actions;

use Illuminate\Container\Attributes\DB;
use Illuminate\Database\Connection;

final readonly class CreateDatabase
{
    public function __construct(
        #[DB] private Connection $connection,
    ) {}

    public function __invoke(string $databaseName): void
    {
        $this->connection->getSchemaBuilder()->createDatabase($databaseName);
    }
}
