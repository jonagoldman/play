<?php

declare(strict_types=1);

namespace Deplox\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Throwable;

final class EnvironmentInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        /** @var Illuminate\Support\Composer */
        $composer = $app->make(\Illuminate\Support\Composer::class);

        return [
            'php' => implode('.', [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]),
            'laravel' => $app->version(),
            'composer' => $composer->getVersion() ?? '-',
            'database' => $this->databaseVersion($app),
        ];
    }

    /**
     * Best-effort database version probe.
     *
     * Tries `select version()` (works on MySQL/MariaDB/Postgres) and falls back
     * to the connection driver name when the query is unsupported (e.g. SQLite)
     * or the connection is unconfigured.
     */
    private function databaseVersion(Application $app): string
    {
        try {
            $resolver = $app->make(\Illuminate\Database\ConnectionResolverInterface::class);
            $version = $resolver->select('select version() as version')[0]->{'version'} ?? null;

            if (is_string($version)) {
                return Str::before($version, ' (');
            }
        } catch (Throwable) {
            // Driver doesn't support `select version()` (e.g. sqlite) or DB unreachable.
        }

        try {
            return $app->make(\Illuminate\Database\ConnectionResolverInterface::class)->getDriverName();
        } catch (Throwable) {
            return '-';
        }
    }
}
