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
        $data = Arr::map($app->getBindings(), function ($concrete, $abstract) use ($app) {
            return ['resolved' => $app->resolved($abstract), 'singleton' => $concrete['shared']];
        });

        return $data;
    }
}
