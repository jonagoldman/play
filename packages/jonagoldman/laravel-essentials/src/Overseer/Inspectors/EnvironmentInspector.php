<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;

final class EnvironmentInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        /** @var Illuminate\Support\Composer */
        $composer = $app->make('composer');

        return [
            'php' => implode('.', [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]),
            'laravel' => $app->version(),
            'composer' => $composer->getVersion() ?? '-',
            'database' => Str::before($app['db']->select('select version() as version')[0]->{'version'}, ' ('),
            // 'MySQL' => $this->app['db']->select('select version()')[0]->{'version()'},
        ];
    }
}
