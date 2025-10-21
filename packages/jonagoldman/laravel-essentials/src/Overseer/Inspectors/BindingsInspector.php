<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;

final class BindingsInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        return Arr::map($app->getBindings(), fn ($concrete, $abstract): array => [
            'resolved' => $app->resolved($abstract),
            'singleton' => $concrete['shared'],
        ]);
    }
}
