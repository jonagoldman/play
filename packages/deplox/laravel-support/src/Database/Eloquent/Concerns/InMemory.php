<?php

declare(strict_types=1);

namespace Deplox\Support\Database\Eloquent\Concerns;

use Closure;
use DateTimeImmutable;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Inspired by https://github.com/calebporzio/sushi
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait InMemory
{
    protected static $sushiConnection;

    /**
     * Per-class pending migration state, populated during boot and consumed on
     * the first connection resolution. Migrations are deferred out of the boot
     * lifecycle because Laravel 13 forbids instantiating a model while it is
     * still booting (see Model::bootIfNotBooted).
     *
     * Value shape: ['cachePath' => string, 'dataPath' => string]|null
     *   - array: cache-file path needs migration AND mtime touch after
     *   - null:  in-memory db needs migration only (no touch)
     *
     * @var array<class-string, array{cachePath: string, dataPath: string}|null>
     */
    protected static array $sushiPendingMigration = [];

    public static function bootInMemory(): void
    {
        $cacheFileName = Str::kebab(str_replace('\\', '', static::class)).'.sqlite';
        $cacheDirectory = App::storagePath('framework/cache/sushi');

        File::ensureDirectoryExists($cacheDirectory);

        $cachePath = $cacheDirectory.DIRECTORY_SEPARATOR.$cacheFileName;
        $dataPath = new ReflectionClass(static::class)->getFileName();
        $shouldCache = property_exists(static::class, 'rows');

        // no-caching-capabilities
        if (! $shouldCache) {
            static::setSqliteConnection(':memory:');
            static::$sushiPendingMigration[static::class] = null;
        }
        // cache-file-found-and-up-to-date
        elseif (File::exists($cachePath) && File::lastModified($dataPath) <= File::lastModified($cachePath)) {
            static::setSqliteConnection($cachePath);
        }
        // cache-file-not-found-or-stale
        else {
            File::put($cachePath, '');
            static::setSqliteConnection($cachePath);
            static::$sushiPendingMigration[static::class] = ['cachePath' => $cachePath, 'dataPath' => $dataPath];
        }
    }

    public static function resolveConnection($connection = null)
    {
        // Run any deferred migration on the first connection resolve. Unset
        // the entry before calling migrate() so the recursive resolveConnection
        // calls Eloquent makes during inserts short-circuit here.
        if (array_key_exists(static::class, static::$sushiPendingMigration)) {
            $pending = static::$sushiPendingMigration[static::class];
            unset(static::$sushiPendingMigration[static::class]);

            new static()->migrate();

            if ($pending !== null) {
                touch($pending['cachePath'], File::lastModified($pending['dataPath']));
            }
        }

        return static::$sushiConnection;
    }

    public function getConnectionName(): string
    {
        return static::class;
    }

    public function getRows()
    {
        return $this->rows;
    }

    public function getSchema()
    {
        return $this->schema ?? [];
    }

    public function migrate(): void
    {
        $rows = $this->getRows();
        $tableName = $this->getTable();

        if (count($rows) > 0) {
            $this->createTable($tableName, $rows[0]);
        } else {
            $this->createTableWithNoData($tableName);
        }

        foreach (array_chunk($rows, 100) ?? [] as $inserts) {
            if (! empty($inserts)) {
                static::insert($inserts);
            }
        }
    }

    public function createTable(string $tableName, $firstRow): void
    {
        $this->createTableSafely($tableName, function ($table) use ($firstRow): void {
            // Add the "id" column if it doesn't already exist in the rows.
            if ($this->incrementing && ! array_key_exists($this->primaryKey, $firstRow)) {
                $table->increments($this->primaryKey);
            }

            foreach ($firstRow as $column => $value) {
                $type = match (true) {
                    is_int($value) => 'integer',
                    is_numeric($value) => 'float',
                    is_string($value) => 'string',
                    $value instanceof DateTimeImmutable => 'dateTime',
                    default => 'string',
                };

                if ($column === $this->primaryKey && $type === 'integer') {
                    $table->increments($this->primaryKey);

                    continue;
                }

                $schema = $this->getSchema();

                $type = $schema[$column] ?? $type;

                $table->{$type}($column)->nullable();
            }

            if ($this->usesTimestamps() && (! in_array('updated_at', array_keys($firstRow)) || ! in_array('created_at', array_keys($firstRow)))) {
                $table->timestamps();
            }
        });
    }

    public function createTableWithNoData(string $tableName): void
    {
        $this->createTableSafely($tableName, function ($table): void {
            $schema = $this->getSchema();

            if ($this->incrementing && ! in_array($this->primaryKey, array_keys($schema))) {
                $table->increments($this->primaryKey);
            }

            foreach ($schema as $name => $type) {
                if ($name === $this->primaryKey && $type === 'integer') {
                    $table->increments($this->primaryKey);

                    continue;
                }

                $table->{$type}($name)->nullable();
            }

            if ($this->usesTimestamps() && (! in_array('updated_at', array_keys($schema)) || ! in_array('created_at', array_keys($schema)))) {
                $table->timestamps();
            }
        });
    }

    protected static function setSqliteConnection($database)
    {
        $config = ['driver' => 'sqlite', 'database' => $database];

        static::$sushiConnection = App::make(ConnectionFactory::class)->make($config);

        Config::set('database.connections.'.static::class, $config);
    }

    protected function sushiCacheReferencePath()
    {
        return new ReflectionClass(static::class)->getFileName();
    }

    protected function sushiShouldCache(): bool
    {
        return property_exists(static::class, 'rows');
    }

    protected function createTableSafely(string $tableName, Closure $callback)
    {
        /** @var \Illuminate\Database\Schema\SQLiteBuilder $schemaBuilder */
        $schemaBuilder = static::resolveConnection()->getSchemaBuilder();

        try {
            $schemaBuilder->create($tableName, $callback);
        } catch (QueryException $e) {
            if (Str::contains($e->getMessage(), 'already exists (SQL: create table')) {
                // This error can happen in rare circumstances due to a race condition.
                // Concurrent requests may both see the necessary preconditions for the table creation, but only one can actually succeed.
                return;
            }

            throw $e;
        }
    }
}
