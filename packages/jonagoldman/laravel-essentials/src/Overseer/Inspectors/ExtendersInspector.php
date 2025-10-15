<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use ReflectionClass;

final class ExtendersInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        $appReflection = new ReflectionClass($app);
        $property = $appReflection->getProperty('extenders');

        $data = Arr::map($property->getValue($app), function ($value) {
            return count($value);
        });

        return $data;
    }
}
