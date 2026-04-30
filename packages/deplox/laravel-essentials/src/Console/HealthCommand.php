<?php

declare(strict_types=1);

namespace Deplox\Essentials\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\QueueManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(
    name: 'health',
    description: 'Composite health check (database, cache, queue).',
)]
final class HealthCommand extends Command
{
    public function handle(
        ConnectionResolverInterface $db,
        CacheRepository $cache,
        QueueManager $queue,
    ): int {
        $checks = [
            'database' => fn (): bool => $db->connection()->getPdo() !== null,
            'cache' => function () use ($cache): bool {
                $key = '__health__';
                $cache->put($key, '1', 5);

                return $cache->get($key) === '1';
            },
            'queue' => fn (): bool => $queue->connection()->getConnectionName() !== null
                || $queue->connection() !== null,
        ];

        $allOk = true;

        foreach ($checks as $name => $check) {
            try {
                $ok = (bool) $check();
            } catch (Throwable $e) {
                $ok = false;
                $this->components->error(sprintf('%s: %s', $name, $e->getMessage()));
            }

            $allOk = $allOk && $ok;

            if ($ok) {
                $this->components->info(sprintf('%s: ok', $name));
            } else {
                $this->components->error(sprintf('%s: failing', $name));
            }
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
