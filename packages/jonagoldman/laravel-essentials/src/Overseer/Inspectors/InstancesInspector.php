<?php

declare(strict_types=1);

namespace JonaGoldman\Essentials\Overseer\Inspectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use ReflectionClass;

final class InstancesInspector
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string>
     */
    public function inspect(Application $app): array
    {
        $appReflection = new ReflectionClass($app);
        $property = $appReflection->getProperty('instances');

        return Arr::map($property->getValue($app), fn ($instance) => is_string($instance) ? $instance : $instance::class);
    }
}
